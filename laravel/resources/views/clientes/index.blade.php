<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Clientes</h2>
            <a href="{{ route('clientes.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Nuevo cliente
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="GET" action="{{ route('clientes.index') }}" class="mb-6 flex flex-wrap items-end gap-4 bg-white p-4 shadow-sm sm:rounded-lg">
                <div>
                    <x-input-label for="q" value="Buscar por nombre o NIT" />
                    <x-text-input id="q" name="q" type="search" class="mt-1 block" :value="$filtros['q'] ?? ''" />
                </div>
                <div>
                    <x-input-label for="vendedor_id" value="Vendedor" />
                    <select id="vendedor_id" name="vendedor_id" class="mt-1 block border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">Todos los visibles</option>
                        <option value="sin_asignar" @selected(($filtros['vendedor_id'] ?? '') === 'sin_asignar')>Sin asignar</option>
                        @foreach ($vendedores as $vendedor)
                            <option value="{{ $vendedor->id }}" @selected((string) ($filtros['vendedor_id'] ?? '') === (string) $vendedor->id)>
                                {{ $vendedor->name }}{{ $vendedor->activo ? '' : ' (vendedor desactivado)' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button>Buscar</x-primary-button>
                <a href="{{ route('clientes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
            </form>

            <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nombre</th>
                            <th class="px-4 py-3 font-medium">NIT</th>
                            <th class="px-4 py-3 font-medium">Teléfono</th>
                            <th class="px-4 py-3 font-medium">Vendedor</th>
                            <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($clientes as $cliente)
                            <tr>
                                <td class="px-4 py-3">{{ $cliente->nombre }}</td>
                                <td class="px-4 py-3">{{ $cliente->nit ?? 'CF' }}</td>
                                <td class="px-4 py-3">{{ $cliente->telefono }}</td>
                                <td class="px-4 py-3">
                                    @if ($cliente->vendedor)
                                        {{ $cliente->vendedor->name }}
                                        @unless ($cliente->vendedor->activo)<span class="text-red-700"> (vendedor desactivado)</span>@endunless
                                    @else
                                        <span class="text-gray-500">Sin asignar</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('clientes.edit', $cliente) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                    <form method="POST" action="{{ route('clientes.asignar', $cliente) }}" class="inline-flex items-center ms-3 gap-1">
                                        @csrf
                                        @method('PATCH')
                                        <select name="vendedor_id" aria-label="Vendedor para {{ $cliente->nombre }}" class="border-gray-300 rounded-md text-xs">
                                            @foreach ($asignables as $vendedor)
                                                <option value="{{ $vendedor->id }}" @selected($cliente->vendedor_id === $vendedor->id)>{{ $vendedor->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="text-indigo-600 hover:text-indigo-900">Asignar</button>
                                    </form>
                                    @if ($cliente->vendedor_id !== null)
                                        <form method="POST" action="{{ route('clientes.liberar', $cliente) }}" class="inline ms-3">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-900">Liberar</button>
                                        </form>
                                    @endif
                                    @if (Auth::user()->esAdministrador())
                                        <form method="POST" action="{{ route('clientes.destroy', $cliente) }}" class="inline ms-3">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">Eliminar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No hay clientes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $clientes->links() }}</div>
        </div>
    </div>
</x-app-layout>
