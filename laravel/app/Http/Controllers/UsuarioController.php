<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarUsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Administración de cuentas (L2). Solo el administrador (ruta con rol:administrador).
 * No hay destroy: las cuentas no se borran, se desactivan (L2.5).
 */
class UsuarioController extends Controller
{
    public function index(): View
    {
        $usuarios = User::with('supervisor')
            ->orderBy('rol') // ENUM: MySQL ordena por la posición (administrador, supervisor, vendedor)
            ->orderBy('name')
            ->paginate(20);

        return view('usuarios.index', ['usuarios' => $usuarios]);
    }

    public function create(): View
    {
        return view('usuarios.create', [
            'usuario' => new User, // el formulario marca «activa» por omisión
            'supervisores' => $this->supervisoresActivos(),
        ]);
    }

    public function store(GuardarUsuarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $usuario = new User;
        $this->asignar($usuario, $datos);
        $usuario->password = Hash::make($datos['password']); // Solo Laravel genera hashes (S1).
        $usuario->save();

        return redirect()->route('usuarios.index')->with('status', 'Cuenta creada.');
    }

    public function edit(User $usuario): View
    {
        return view('usuarios.edit', [
            'usuario' => $usuario,
            'supervisores' => $this->supervisoresActivos(),
        ]);
    }

    public function update(GuardarUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $datos = $request->validated();

        DB::transaction(function () use ($usuario, $datos) {
            // Se bloquea la fila de la cuenta y se repite la comprobación de D6: así un cambio de rol
            // no se cruza con una asignación de clientes simultánea (que bloquea la misma fila).
            $bloqueado = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();

            $motivo = $bloqueado->motivoParaNoCambiarRol($datos['rol']);
            if ($motivo !== null) {
                throw ValidationException::withMessages(['rol' => $motivo]);
            }

            $this->asignar($bloqueado, $datos);
            if (! empty($datos['password'])) {
                $bloqueado->password = Hash::make($datos['password']);
            }
            $bloqueado->save();
        });

        return redirect()->route('usuarios.index')->with('status', 'Cuenta actualizada.');
    }

    /** Asigna campo por campo (rol, supervisor_id y activo no son asignables en masa). */
    private function asignar(User $usuario, array $datos): void
    {
        $usuario->name = $datos['name'];
        $usuario->email = $datos['email'];
        $usuario->rol = $datos['rol'];
        $usuario->supervisor_id = $datos['rol'] === 'vendedor' ? (int) $datos['supervisor_id'] : null; // L2.4
        $usuario->activo = (bool) $datos['activo'];
    }

    private function supervisoresActivos()
    {
        return User::where('rol', 'supervisor')->where('activo', true)->orderBy('name')->get(['id', 'name']);
    }
}
