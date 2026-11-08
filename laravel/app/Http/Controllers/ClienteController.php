<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarClienteRequest;
use App\Models\Cliente;
use App\Models\User;
use App\Support\Nit;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClienteController extends Controller
{
    private const MENSAJE_CARTERA_LLENA = 'La cartera del vendedor ya tiene 10 clientes, el máximo permitido.';

    private const MENSAJE_VENDEDOR_INVALIDO = 'El vendedor debe ser una cuenta de vendedor activa de su equipo.';

    public function index(Request $request): View
    {
        // Un filtro con forma de arreglo (?q[]=x, ?vendedor_id[]=4) no lo envía el formulario:
        // responde 400, como la API (spec 3.3), en lugar del 500 de convertir un arreglo en texto.
        foreach (['q', 'vendedor_id'] as $filtro) {
            abort_if(is_array($request->query($filtro)), 400);
        }

        $usuario = $request->user();
        $consulta = Cliente::query()->visiblesPara($usuario)->with('vendedor')->orderBy('nombre');

        if ($request->filled('q')) {
            $busqueda = trim((string) $request->query('q'));
            $nit = Nit::compactar($busqueda);

            // P-8 «contiene»: % y _ se buscan como texto (escapados con \, el escape por omisión
            // de LIKE en MySQL), igual que en la API. El valor va enlazado como parámetro.
            $consulta->where(function ($query) use ($busqueda, $nit) {
                $query->where('nombre', 'like', '%'.addcslashes($busqueda, '\\%_').'%');
                if ($nit !== '') {
                    $query->orWhere('nit', 'like', '%'.addcslashes($nit, '\\%_').'%');
                }
            });
        }

        if ($request->query('vendedor_id') === 'sin_asignar') {
            $consulta->whereNull('vendedor_id');
        } elseif (is_numeric($request->query('vendedor_id'))) {
            $consulta->where('vendedor_id', (int) $request->query('vendedor_id'));
        }

        return view('clientes.index', [
            'clientes' => $consulta->paginate(20)->withQueryString(),
            'vendedores' => User::query()->equipoDe($usuario)->orderBy('name')->get(['id', 'name', 'activo']),
            'asignables' => User::query()->asignablesPor($usuario)->orderBy('name')->get(['id', 'name']),
            'filtros' => $request->only(['q', 'vendedor_id']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('clientes.create', ['cliente' => new Cliente]);
    }

    public function store(GuardarClienteRequest $request): RedirectResponse
    {
        try {
            $cliente = new Cliente;
            $this->asignarDatos($cliente, $request->validated());
            $cliente->save();
        } catch (QueryException $exception) {
            $this->lanzarErrorDuplicado($exception, $request->validated());
            throw $exception;
        }

        return redirect()->route('clientes.index')->with('status', 'Cliente creado y disponible.');
    }

    // edit, update, asignar y liberar: la ruta exige `can:gestionar,cliente` (ClientePolicy),
    // que responde 404 a un cliente de otro equipo antes de llegar aquí y antes de validar.

    public function edit(Cliente $cliente): View
    {
        return view('clientes.edit', ['cliente' => $cliente]);
    }

    public function update(GuardarClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $this->asignarDatos($cliente, $datos);
            $cliente->save();
        } catch (QueryException $exception) {
            $this->lanzarErrorDuplicado($exception, $datos);
            throw $exception;
        }

        return redirect()->route('clientes.index')->with('status', 'Cliente actualizado.');
    }

    /**
     * Asignar (cliente disponible) o reasignar (cliente con vendedor). D2: las oportunidades
     * ABIERTAS pasan al vendedor nuevo en la misma transacción; las cerradas (ganadas y perdidas)
     * se quedan con su vendedor original, y ninguna actividad se modifica.
     */
    public function asignar(Request $request, Cliente $cliente): RedirectResponse
    {
        $datos = $request->validate(
            ['vendedor_id' => ['required', 'integer']],
            [
                'vendedor_id.required' => 'Elija un vendedor.',
                'vendedor_id.integer' => 'Elija un vendedor válido.',
            ],
        );

        DB::transaction(function () use ($request, $cliente, $datos): void {
            // Orden de bloqueos (igual en liberar y en la API): primero users, luego clientes.
            // Solo vendedores ACTIVOS de su equipo (el administrador: de toda la empresa). Un id de
            // otro equipo, desactivado o que no es vendedor no encuentra fila: mensaje junto al campo.
            $usuario = $request->user();
            $vendedor = User::query()->asignablesPor($usuario)
                ->whereKey($datos['vendedor_id'])->lockForUpdate()->first();

            if ($vendedor === null) {
                throw ValidationException::withMessages(['vendedor_id' => self::MENSAJE_VENDEDOR_INVALIDO]);
            }

            // Se vuelve a comprobar la visibilidad con el cliente ya bloqueado: si el administrador
            // lo pasó a otro equipo después del 404 de la ruta, ya no es de este supervisor.
            $bloqueado = Cliente::query()->visiblesPara($usuario)
                ->whereKey($cliente->id)->lockForUpdate()->first();
            abort_if($bloqueado === null, 404);

            if ($bloqueado->vendedor_id === $vendedor->id) {
                return;
            }

            // R3: se cuenta con la fila del vendedor ya bloqueada (otra asignación, una toma o un
            // alta por la API esperan aquí).
            if (Cliente::where('vendedor_id', $vendedor->id)->count() >= Cliente::MAXIMO_CARTERA) {
                throw ValidationException::withMessages(['vendedor_id' => self::MENSAJE_CARTERA_LLENA]);
            }

            $bloqueado->vendedor_id = $vendedor->id;
            $bloqueado->save();

            // D2: solo las abiertas cambian de dueño; las cerradas conservan la venta (y el reporte).
            DB::table('oportunidades')
                ->where('cliente_id', $bloqueado->id)
                ->whereNotIn('etapa', ['ganada', 'perdida'])
                ->update(['vendedor_id' => $vendedor->id, 'updated_at' => now()]);
        });

        return redirect()->route('clientes.index')->with('status', 'Cliente asignado o reasignado correctamente.');
    }

    /**
     * D3: no se libera un cliente con oportunidades abiertas (mensaje del panel de D3). Con solo
     * cerradas sí se libera; las cerradas siguen con su vendedor original.
     */
    public function liberar(Request $request, Cliente $cliente): RedirectResponse
    {
        DB::transaction(function () use ($request, $cliente): void {
            // Mismo orden de bloqueos que asignar (y que la API al tomar o crear clientes):
            // primero la fila del vendedor en users, luego la del cliente. Con órdenes distintos,
            // un asignar y un liberar simultáneos podían bloquearse mutuamente (deadlock).
            $usuario = $request->user();
            $vendedorId = Cliente::query()->visiblesPara($usuario)->whereKey($cliente->id)->value('vendedor_id');
            $vendedorId = $vendedorId === null ? null : (int) $vendedorId;

            if ($vendedorId !== null) {
                User::whereKey($vendedorId)->lockForUpdate()->first();
            }

            $bloqueado = Cliente::query()->visiblesPara($usuario)
                ->whereKey($cliente->id)->lockForUpdate()->first();
            abort_if($bloqueado === null, 404);

            // Si entre la lectura y el bloqueo otro proceso lo reasignó, no se libera a ciegas.
            if ($bloqueado->vendedor_id !== $vendedorId) {
                throw ValidationException::withMessages([
                    'cliente' => 'El cliente cambió de vendedor mientras se liberaba. Revise la lista y vuelva a intentarlo.',
                ]);
            }

            if (DB::table('oportunidades')
                ->where('cliente_id', $bloqueado->id)
                ->whereNotIn('etapa', ['ganada', 'perdida'])
                ->exists()) {
                throw ValidationException::withMessages([
                    'cliente' => 'Este cliente tiene oportunidades abiertas. Reasigne el cliente (sus oportunidades abiertas pasan al nuevo vendedor) o pida al vendedor que las cierre.',
                ]);
            }

            $bloqueado->vendedor_id = null;
            $bloqueado->save();
        });

        return redirect()->route('clientes.index')->with('status', 'Cliente liberado.');
    }

    public function destroy(Request $request, Cliente $cliente): RedirectResponse
    {
        // La ruta ya exige rol:administrador; se repite aquí por si la acción se monta en otra ruta.
        abort_unless($request->user()->esAdministrador(), 403);

        DB::transaction(function () use ($cliente): void {
            $bloqueado = Cliente::whereKey($cliente->id)->lockForUpdate()->firstOrFail();

            if (DB::table('oportunidades')->where('cliente_id', $bloqueado->id)->exists()) {
                throw ValidationException::withMessages([
                    'cliente' => 'No puede eliminar un cliente que tiene oportunidades.',
                ]);
            }

            $bloqueado->delete();
        });

        return redirect()->route('clientes.index')->with('status', 'Cliente eliminado.');
    }

    private function asignarDatos(Cliente $cliente, array $datos): void
    {
        $cliente->nombre = $datos['nombre'];
        $cliente->telefono = $datos['telefono'];
        $cliente->correo = $datos['correo'] ?? null;
        // CF se guarda como NULL (R4).
        $cliente->nit = $datos['nit'] === 'CF' ? null : $datos['nit'];
    }

    private function lanzarErrorDuplicado(QueryException $exception, array $datos): void
    {
        if (($exception->errorInfo[1] ?? null) !== 1062) {
            return;
        }

        $mensajeDuplicado = $exception->getMessage();
        $campo = str_contains($mensajeDuplicado, 'clientes_nit_unique')
            ? 'nit'
            : (str_contains($mensajeDuplicado, 'clientes_telefono_unique') ? 'telefono' : null);

        if ($campo === null) {
            $campo = array_key_exists('nit', $datos) ? 'nit' : 'telefono';
        }
        $mensaje = $campo === 'nit'
            ? 'Ya existe un cliente con ese NIT.'
            : 'Ya existe un cliente con ese teléfono.';

        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
