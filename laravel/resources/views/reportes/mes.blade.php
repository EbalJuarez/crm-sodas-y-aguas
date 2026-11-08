@php use App\Support\Dinero; @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Reporte del mes · {{ $reporte['nombre_mes'] }}
            <span class="text-sm font-normal text-gray-500">({{ $esAdministrador ? 'toda la empresa' : 'su equipo' }})</span>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('reportes.mes') }}" class="flex flex-wrap items-end gap-4 bg-white p-4 shadow-sm sm:rounded-lg">
                <div>
                    <x-input-label for="mes" value="Mes (hora de Guatemala)" />
                    <x-text-input id="mes" name="mes" type="month" class="mt-1 block" :value="$reporte['mes']" />
                    <x-input-error :messages="$errors->get('mes')" class="mt-2" />
                </div>
                <x-primary-button>Ver</x-primary-button>
            </form>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">Ventas del mes</p>
                    <p class="text-2xl font-semibold" data-cifra="ventas">{{ $reporte['totales']['ventas'] }}</p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">Total vendido</p>
                    <p class="text-2xl font-semibold" data-cifra="total">{{ Dinero::formatear($reporte['totales']['total']) }}</p>
                </div>
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">Pronóstico (pedidos por cerrar, cualquier mes)</p>
                    <p class="text-2xl font-semibold" data-cifra="pronostico">{{ Dinero::formatear($reporte['totales']['pronostico']) }}</p>
                </div>
            </div>

            <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg">
                <h3 class="px-4 pt-4 font-semibold text-gray-800">Por vendedor</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Vendedor</th>
                            <th class="px-4 py-3 font-medium text-right">Ventas</th>
                            <th class="px-4 py-3 font-medium text-right">Total vendido</th>
                            <th class="px-4 py-3 font-medium text-right">Pronóstico</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($reporte['vendedores'] as $fila)
                            <tr>
                                <td class="px-4 py-3">
                                    {{ $fila['nombre'] }}
                                    @unless ($fila['activo'])<span class="text-red-700"> (vendedor desactivado)</span>@endunless
                                </td>
                                <td class="px-4 py-3 text-right">{{ $fila['ventas'] }}</td>
                                <td class="px-4 py-3 text-right">{{ Dinero::formatear($fila['total']) }}</td>
                                <td class="px-4 py-3 text-right">{{ Dinero::formatear($fila['pronostico']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No hay vendedores.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg">
                <h3 class="px-4 pt-4 font-semibold text-gray-800">Por producto</h3>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Producto</th>
                            <th class="px-4 py-3 font-medium text-right">Unidades</th>
                            <th class="px-4 py-3 font-medium text-right">Total vendido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($reporte['productos'] as $fila)
                            <tr>
                                <td class="px-4 py-3">{{ $fila['nombre'] }}</td>
                                <td class="px-4 py-3 text-right">{{ $fila['cantidad'] }}</td>
                                <td class="px-4 py-3 text-right">{{ Dinero::formatear($fila['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No hubo ventas en este mes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
