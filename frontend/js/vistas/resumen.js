import { alerta, h, spinner } from '../util/dom.js';

const MESES = [
    ['01', 'Enero'], ['02', 'Febrero'], ['03', 'Marzo'], ['04', 'Abril'], ['05', 'Mayo'], ['06', 'Junio'],
    ['07', 'Julio'], ['08', 'Agosto'], ['09', 'Septiembre'], ['10', 'Octubre'], ['11', 'Noviembre'], ['12', 'Diciembre'],
];

export function crearVistaResumen({ reporte }) {
    const [anio, mes] = reporte.mes.split('-');
    const anioActual = Number(anio || new Date().getUTCFullYear());
    const anios = Array.from({ length: 5 }, (_, indice) => anioActual - 2 + indice);

    return h('section', { class: 'vstack gap-3' },
        h('div', {},
            h('h2', { class: 'h4 mb-1', text: 'Resumen mensual' }),
            h('p', { class: 'text-secondary mb-0', text: 'Consulte sus ventas y pronóstico por mes.' })),
        h('div', { class: 'card card-body home-panel' },
            h('div', { class: 'row g-2 align-items-end' },
                h('div', { class: 'col-12 col-sm-6 col-md-3' },
                    h('label', { class: 'form-label', htmlFor: 'resumen-mes', text: 'Mes' }),
                    h('select', { class: 'form-select', id: 'resumen-mes', name: 'resumen-mes', value: mes },
                        MESES.map(([clave, nombre]) => h('option', { value: clave }, nombre)))),
                h('div', { class: 'col-12 col-sm-6 col-md-3' },
                    h('label', { class: 'form-label', htmlFor: 'resumen-anio', text: 'Año' }),
                    h('select', { class: 'form-select', id: 'resumen-anio', name: 'resumen-anio', value: anio },
                        anios.map((numero) => h('option', { value: String(numero) }, String(numero))))))),
        reporte.error && alerta('danger', reporte.error),
        reporte.cargando
            ? h('div', { class: 'text-secondary py-4' }, spinner('Cargando resumen…'))
            : reporte.datos && tarjetas(reporte.datos));
}

function tarjetas(datos) {
    return h('div', { class: 'row g-3' },
        indicador('Ventas', String(datos.ventas), 'ventas'),
        indicador('Total vendido', quetzales(datos.total), 'total'),
        indicador('Pronóstico', quetzales(datos.pronostico), 'pronostico'),
        indicador('Cartera', `${datos.cartera.clientes} de ${datos.cartera.maximo}`, 'cartera'));
}

function indicador(titulo, valor, clase) {
    return h('div', { class: 'col-12 col-sm-6 col-xl-3' },
        h('article', { class: `card home-panel summary-card summary-${clase}` },
            h('div', { class: 'card-body' },
                h('p', { class: 'small text-secondary mb-2', text: titulo }),
                h('p', { class: 'h3 mb-0 text-break', text: valor }))));
}

function quetzales(valor) {
    return new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' }).format(Number(valor));
}
