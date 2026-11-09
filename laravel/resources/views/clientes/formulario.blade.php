<div class="space-y-6">
    <div>
        <x-input-label for="nombre" value="Nombre" />
        <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="old('nombre', $cliente->nombre)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('nombre')" />
    </div>

    <div>
        <x-input-label for="nit" value="NIT o CF" />
        <x-text-input id="nit" name="nit" type="text" class="mt-1 block w-full" :value="old('nit', $cliente->nit ?? 'CF')" required />
        <x-input-error class="mt-2" :messages="$errors->get('nit')" />
    </div>

    <div>
        <x-input-label for="telefono" value="Teléfono" />
        <x-text-input id="telefono" name="telefono" type="text" class="mt-1 block w-full" :value="old('telefono', $cliente->telefono)" required />
        <x-input-error class="mt-2" :messages="$errors->get('telefono')" />
    </div>

    <div>
        <x-input-label for="correo" value="Correo (opcional)" />
        <x-text-input id="correo" name="correo" type="email" class="mt-1 block w-full" :value="old('correo', $cliente->correo)" />
        <x-input-error class="mt-2" :messages="$errors->get('correo')" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ $cliente->exists ? 'Guardar cambios' : 'Crear cliente' }}</x-primary-button>
        <a href="{{ route('clientes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
    </div>
</div>
