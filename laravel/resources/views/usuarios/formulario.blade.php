{{-- Formulario compartido de alta y edición de cuentas. Todo con {{ }} (S7). --}}
@php($esEdicion = $usuario->exists)

<div class="space-y-6" x-data="{ rol: @js(old('rol', $usuario->rol ?? 'vendedor')) }">
    <div>
        <x-input-label for="name" value="Nombre" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $usuario->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="email" value="Correo" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $usuario->email)" required />
        <x-input-error class="mt-2" :messages="$errors->get('email')" />
    </div>

    <div>
        <x-input-label for="password" :value="$esEdicion ? 'Contraseña nueva (déjela vacía para no cambiarla)' : 'Contraseña'" />
        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" :required="! $esEdicion" />
        <x-input-error class="mt-2" :messages="$errors->get('password')" />
    </div>

    <div>
        <x-input-label for="password_confirmation" value="Confirme la contraseña" />
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
    </div>

    <div>
        <x-input-label for="rol" value="Rol" />
        <select id="rol" name="rol" x-model="rol" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            @foreach (\App\Models\User::ROLES as $rol)
                <option value="{{ $rol }}" @selected(old('rol', $usuario->rol) === $rol)>{{ ucfirst($rol) }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('rol')" />
    </div>

    {{-- Solo para vendedores. Si se envía con otro rol, el servidor lo descarta (L2.4). --}}
    <div x-show="rol === 'vendedor'">
        <x-input-label for="supervisor_id" value="Supervisor" />
        <select id="supervisor_id" name="supervisor_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">Elija un supervisor</option>
            @foreach ($supervisores as $supervisor)
                <option value="{{ $supervisor->id }}" @selected((string) old('supervisor_id', $usuario->supervisor_id) === (string) $supervisor->id)>{{ $supervisor->name }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('supervisor_id')" />
    </div>

    <div>
        <input type="hidden" name="activo" value="0">
        <label for="activo" class="inline-flex items-center">
            <input id="activo" name="activo" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                   @checked(old('activo', $usuario->activo ?? true))>
            <span class="ms-2 text-sm text-gray-700">Cuenta activa</span>
        </label>
        <p class="mt-1 text-xs text-gray-500">Las cuentas no se borran: se desactivan.</p>
        <x-input-error class="mt-2" :messages="$errors->get('activo')" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ $esEdicion ? 'Guardar cambios' : 'Crear cuenta' }}</x-primary-button>
        <a href="{{ route('usuarios.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
    </div>
</div>
