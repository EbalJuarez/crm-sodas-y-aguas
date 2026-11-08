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
        $usuario = $request->user();
        $consulta = Cliente::query()->with('vendedor')->orderBy('nombre');
        $this->aplicarVisibilidad($consulta, $usuario);

        if ($request->filled('q')) {
            $busqueda = trim((string) $request->query('q'));
            $nit = Nit::compactar($busqueda);

            $consulta->where(function ($query) use ($busqueda, $nit) {
                $query->where('nombre', 'like', "%{$busqueda}%");
                if ($nit !== '') {
                    $query->orWhere('nit', 'like', "%{$nit}%");
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
            'vendedores' => $this->vendedoresVisibles($usuario),
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

    public function edit(Request $request, Cliente $cliente): View
    {
        $this->autorizarVisibilidad($request->user(), $cliente);

        return view('clientes.edit', ['cliente' => $cliente]);
    }

    public function update(GuardarClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizarVisibilidad($request->user(), $cliente);
        $datos = $request->validated();

        if ($datos === []) {
            abort(400);
        }

        try {
            $this->asignarDatos($cliente, $datos, parcial: true);
            $cliente->save();
        } catch (QueryException $exception) {
            $this->lanzarErrorDuplicado($exception, $datos);
            throw $exception;
        }

        return redirect()->route('clientes.index')->with('status', 'Cliente actualizado.');
    }

    public function asignar(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizarVisibilidad($request->user(), $cliente);
        $datos = $request->validate(
            ['vendedor_id' => ['required', 'integer']],
            [
                'vendedor_id.required' => 'Elija un vendedor.',
                'vendedor_id.integer' => 'Elija un vendedor válido.',
            ],
        );

        DB::transaction(function () use ($request, $cliente, $datos): void {
            $usuario = $request->user();
            $vendedor = User::whereKey($datos['vendedor_id'])->lockForUpdate()->first();

            if (! $this->puedeAsignarA($usuario, $vendedor)) {
                throw ValidationException::withMessages(['vendedor_id' => self::MENSAJE_VENDEDOR_INVALIDO]);
            }

            $bloqueado = Cliente::whereKey($cliente->id)->lockForUpdate()->firstOrFail();
            if ($bloqueado->vendedor_id === $vendedor->id) {
                return;
            }

            if (Cliente::where('vendedor_id', $vendedor->id)->count() >= 10) {
                throw ValidationException::withMessages(['vendedor_id' => self::MENSAJE_CARTERA_LLENA]);
            }

            $bloqueado->vendedor_id = $vendedor->id;
            $bloqueado->save();

            DB::table('oportunidades')
                ->where('cliente_id', $bloqueado->id)
                ->whereNotIn('etapa', ['ganada', 'perdida'])
                ->update(['vendedor_id' => $vendedor->id, 'updated_at' => now()]);
        });

        return redirect()->route('clientes.index')->with('status', 'Cliente asignado o reasignado correctamente.');
    }

    public function liberar(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizarVisibilidad($request->user(), $cliente);

        DB::transaction(function () use ($cliente): void {
            $bloqueado = Cliente::whereKey($cliente->id)->lockForUpdate()->firstOrFail();

            if ($bloqueado->vendedor_id !== null) {
                User::whereKey($bloqueado->vendedor_id)->lockForUpdate()->first();
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
        $this->autorizarVisibilidad($request->user(), $cliente);

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

    private function asignarDatos(Cliente $cliente, array $datos, bool $parcial = false): void
    {
        foreach (['nombre', 'telefono', 'correo'] as $campo) {
            if ($parcial && ! array_key_exists($campo, $datos)) {
                continue;
            }
            $cliente->{$campo} = $datos[$campo] ?? null;
        }

        if (! $parcial || array_key_exists('nit', $datos)) {
            $cliente->nit = ($datos['nit'] ?? null) === 'CF' ? null : ($datos['nit'] ?? null);
        }
    }

    private function aplicarVisibilidad($consulta, User $usuario): void
    {
        if ($usuario->esAdministrador()) {
            return;
        }

        $consulta->where(function ($query) use ($usuario) {
            $query->whereNull('vendedor_id')
                ->orWhereHas('vendedor', fn ($vendedor) => $vendedor->where('supervisor_id', $usuario->id));
        });
    }

    private function autorizarVisibilidad(User $usuario, Cliente $cliente): void
    {
        abort_unless($cliente->esVisiblePara($usuario), 404);
    }

    private function vendedoresVisibles(User $usuario)
    {
        $consulta = User::query()->where('rol', 'vendedor')->orderBy('name');

        if (! $usuario->esAdministrador()) {
            $consulta->where('supervisor_id', $usuario->id);
        }

        return $consulta->get(['id', 'name', 'activo']);
    }

    private function puedeAsignarA(User $usuario, ?User $vendedor): bool
    {
        return $vendedor !== null
            && $vendedor->rol === 'vendedor'
            && $vendedor->activo
            && ($usuario->esAdministrador() || $vendedor->supervisor_id === $usuario->id);
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
