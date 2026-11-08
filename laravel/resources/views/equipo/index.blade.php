@php use App\Support\Dinero; use App\Support\ZonaGuatemala; @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $esAdministrador ? 'Vendedores' : 'Mi equipo' }} · {{ ZonaGuatemala::nombreDelMes($mes) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('equipo.index') }}" class="flex flex-wrap items-end gap-4 bg-white p-4 shadow-sm sm:rounded-lg">
                <div>
                    <x-input-label for="mes" value="Ventas del mes (hora de Guatemala)" />
                    <x-text-input id="mes" name="mes" type="month" class="mt-1 block" :value="$mes" />
                    <x-input-error :messages="$errors->get('mes')" class="mt-2" />
                </div>
                <x-primary-button>Ver</x-primary-button>
            </form>

            <p class="text-sm text-gray-600">
                Un vendedor desactivado conserva su cartera hasta que usted reasigne sus puntos de venta
                desde «Clientes», y no recibe puntos de venta nuevos.
            </p>

            <div class="bg-white overflow-x-auto shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Vendedor</th>
                            <th class="px-4 py-3 font-medium text-right">Cartera</th>
                            <th class="px-4 py-3 font-medium text-right">Ventas</th>
                            <th class="px-4 py-3 font-medium text-right">Total vendido</th>
                            <th class="px-4 py-3 font-medium text-right">Pronóstico</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($equipo as $fila)
                            <tr>
                                <td class="px-4 py-3">
                                    {{ $fila['nombre'] }}
                                    @unless ($fila['activo'])<span class="text-red-700"> (vendedor desactivado)</span>@endunless
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('clientes.index', ['vendedor_id' => $fila['id']]) }}" class="text-indigo-600 hover:text-indigo-900">
                                        {{ $fila['cartera'] }} de {{ $fila['maximo'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-right">{{ $fila['ventas'] }}</td>
                                <td class="px-4 py-3 text-right">{{ Dinero::formatear($fila['total']) }}</td>
                                <td class="px-4 py-3 text-right">{{ Dinero::formatear($fila['pronostico']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No tiene vendedores a su cargo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
