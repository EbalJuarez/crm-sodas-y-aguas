<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Catálogo de productos</h2>
            <a href="{{ route('productos.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Nuevo producto
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nombre</th>
                            <th class="px-4 py-3 font-medium">Precio</th>
                            <th class="px-4 py-3 font-medium">Unidades por caja</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($productos as $producto)
                            <tr>
                                <td class="px-4 py-3">{{ $producto->nombre }}</td>
                                <td class="px-4 py-3">Q{{ number_format((float) $producto->precio, 2, '.', ',') }}</td>
                                <td class="px-4 py-3">{{ $producto->unidades_por_caja ?? 'Por unidad' }}</td>
                                <td class="px-4 py-3">
                                    @if ($producto->activo)
                                        <span class="text-green-700">Activo</span>
                                    @else
                                        <span class="text-red-700">Desactivado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right space-x-3">
                                    <a href="{{ route('productos.edit', $producto) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                    <form method="POST" action="{{ route('productos.estado', $producto) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="activo" value="{{ $producto->activo ? '0' : '1' }}">
                                        <button type="submit" class="text-indigo-600 hover:text-indigo-900">
                                            {{ $producto->activo ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No hay productos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $productos->links() }}</div>
        </div>
    </div>
</x-app-layout>
