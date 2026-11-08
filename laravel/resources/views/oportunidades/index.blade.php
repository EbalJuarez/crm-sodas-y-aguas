@php
    use App\Support\Dinero;
    use App\Support\ZonaGuatemala;

    $nombresEtapa = [
        'prospecto' => 'Prospecto', 'calificado' => 'Calificado', 'propuesta' => 'Propuesta',
        'negociacion' => 'Negociación', 'ganada' => 'Ganada', 'perdida' => 'Perdida',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pedidos por cerrar</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-600">Solo lectura: los vendedores crean y avanzan sus pedidos desde su aplicación. Fechas en hora de Guatemala.</p>

            <form method="GET" action="{{ route('oportunidades.index') }}" class="mb-6 flex flex-wrap items-end gap-4 bg-white p-4 shadow-sm sm:rounded-lg">
                <div>
                    <x-input-label for="etapa" value="Etapa" />
                    <select id="etapa" name="etapa" class="mt-1 block border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">Todas</option>
                        @foreach ($nombresEtapa as $valor => $nombre)
                            <option value="{{ $valor }}" @selected($filtros['etapa'] === $valor)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="vendedor_id" value="Vendedor" />
                    <select id="vendedor_id" name="vendedor_id" class="mt-1 block border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">Todos los visibles</option>
                        @foreach ($vendedores as $vendedor)
                            <option value="{{ $vendedor->id }}" @selected((string) $filtros['vendedor_id'] === (string) $vendedor->id)>
                                {{ $vendedor->name }}{{ $vendedor->activo ? '' : ' (vendedor desactivado)' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button>Filtrar</x-primary-button>
                <a href="{{ route('oportunidades.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
            </form>

            <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Título</th>
                            <th class="px-4 py-3 font-medium">Punto de venta</th>
                            <th class="px-4 py-3 font-medium">Vendedor</th>
                            <th class="px-4 py-3 font-medium">Etapa</th>
                            <th class="px-4 py-3 font-medium text-right">Total</th>
                            <th class="px-4 py-3 font-medium">Fecha de cierre</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($oportunidades as $oportunidad)
                            <tr>
                                <td class="px-4 py-3">{{ $oportunidad->titulo }}</td>
                                <td class="px-4 py-3">{{ $oportunidad->cliente }}</td>
                                <td class="px-4 py-3">
                                    {{ $oportunidad->vendedor }}
                                    @unless ($oportunidad->vendedor_activo)<span class="text-red-700"> (vendedor desactivado)</span>@endunless
                                </td>
                                <td class="px-4 py-3">{{ $nombresEtapa[$oportunidad->etapa] ?? $oportunidad->etapa }}</td>
                                <td class="px-4 py-3 text-right">{{ Dinero::formatear($oportunidad->total) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ ZonaGuatemala::formatear($oportunidad->fecha_cierre) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay pedidos con estos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $oportunidades->links() }}</div>
        </div>
    </div>
</x-app-layout>
