<div class="space-y-6">
    <div>
        <x-input-label for="nombre" value="Nombre" />
        <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="old('nombre', $producto->nombre)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('nombre')" />
    </div>

    <div>
        <x-input-label for="precio" value="Precio" />
        <x-text-input id="precio" name="precio" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('precio', $producto->precio)" required />
        <x-input-error class="mt-2" :messages="$errors->get('precio')" />
    </div>

    <div>
        <x-input-label for="unidades_por_caja" value="Unidades por caja (opcional)" />
        <x-text-input id="unidades_por_caja" name="unidades_por_caja" type="number" min="1" max="999" step="1" class="mt-1 block w-full" :value="old('unidades_por_caja', $producto->unidades_por_caja)" />
        <p class="mt-1 text-xs text-gray-500">Déjelo vacío si se vende por unidad.</p>
        <x-input-error class="mt-2" :messages="$errors->get('unidades_por_caja')" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ $producto->exists ? 'Guardar cambios' : 'Crear producto' }}</x-primary-button>
        <a href="{{ route('productos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
    </div>
</div>
