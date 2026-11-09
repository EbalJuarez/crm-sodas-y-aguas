import { alerta, h, spinner } from '../util/dom.js';

const ABIERTAS = [
    ['prospecto', 'Prospecto'],
    ['calificado', 'Calificado'],
    ['propuesta', 'Propuesta'],
    ['negociacion', 'Negociación'],
];

export function crearVistaEmbudo({ oportunidades, cartera }) {
    return h('section', { class: 'vstack gap-3' },
        h('div', { class: 'd-flex flex-wrap align-items-center justify-content-between gap-2' },
            h('div', {},
                h('h2', { class: 'h4 mb-1', text: 'Pedidos por cerrar' }),
                h('p', { class: 'text-secondary mb-0', text: 'Avance de sus oportunidades y pedidos.' })),
            !oportunidades.nueva.abierta && h('button', {
                class: 'btn btn-agua btn-sm', type: 'button', dataset: { action: 'abrir-nueva-oportunidad' },
            }, 'Nueva oportunidad')),
        oportunidades.nueva.abierta && formularioNueva(oportunidades.nueva, cartera.clientes),
        oportunidades.error && alerta('danger', oportunidades.error),
        !oportunidades.cargada
            ? h('div', { class: 'text-secondary py-4' }, spinner('Cargando pedidos por cerrar…'))
            : tablero(oportunidades.lista));
}

function formularioNueva(nueva, clientes) {
    return h('form', { class: 'card card-body shadow-sm vstack gap-3', dataset: { form: 'crear-oportunidad' }, noValidate: true },
        h('h3', { class: 'h5 mb-0', text: 'Nueva oportunidad' }),
        h('div', {},
            h('label', { class: 'form-label', htmlFor: 'nueva-cliente', text: 'Punto de venta' }),
            h('select', {
                class: `form-select${nueva.errores.cliente_id ? ' is-invalid' : ''}`,
                id: 'nueva-cliente', name: 'cliente_id', value: nueva.datos.cliente_id,
                'aria-invalid': nueva.errores.cliente_id ? 'true' : null,
            },
            h('option', { value: '' }, 'Elija un punto de venta'),
            clientes.map((cliente) => h('option', { value: String(cliente.id) }, cliente.nombre)),
            nueva.errores.cliente_id && h('div', { class: 'invalid-feedback', text: nueva.errores.cliente_id }))),
        campo('nueva-titulo', 'Título', 'titulo', nueva.datos.titulo, nueva.errores.titulo),
        nueva.error && alerta('danger', nueva.error),
        h('div', { class: 'd-flex flex-wrap gap-2' },
            h('button', { class: 'btn btn-agua', type: 'submit', disabled: nueva.enviando }, nueva.enviando ? 'Guardando…' : 'Crear'),
            h('button', { class: 'btn btn-outline-secondary', type: 'button', disabled: nueva.enviando, dataset: { action: 'cancelar-nueva-oportunidad' } }, 'Cancelar')));
}

function campo(id, etiqueta, nombre, valor, error) {
    return h('div', {},
        h('label', { class: 'form-label', htmlFor: id, text: etiqueta }),
        h('input', {
            class: `form-control${error ? ' is-invalid' : ''}`, id, name: nombre, type: 'text', value: valor,
            'aria-invalid': error ? 'true' : null, 'aria-describedby': error ? `${id}-error` : null,
        }),
        error && h('div', { class: 'invalid-feedback', id: `${id}-error`, text: error }));
}

function tablero(lista) {
    const columnas = ABIERTAS.map(([clave, titulo]) => h('section', { class: 'funnel-column' },
        h('div', { class: 'd-flex justify-content-between align-items-center mb-2' },
            h('h3', { class: 'h6 mb-0', text: titulo }),
            h('span', { class: 'badge text-bg-light', text: String(lista.filter((oportunidad) => oportunidad.etapa === clave).length) })),
        h('div', { class: 'vstack gap-2' },
            lista.filter((oportunidad) => oportunidad.etapa === clave).map(tarjeta),
            lista.some((oportunidad) => oportunidad.etapa === clave) ? null : h('p', { class: 'small text-secondary mb-0', text: 'Sin oportunidades.' }))));
    const cerradas = lista.filter((oportunidad) => ['ganada', 'perdida'].includes(oportunidad.etapa));

    return h('div', { class: 'vstack gap-4' },
        h('div', { class: 'funnel-board' }, columnas),
        h('section', { class: 'card home-panel' },
            h('div', { class: 'card-body' },
                h('h3', { class: 'h5', text: 'Cerradas' }),
                cerradas.length === 0
                    ? h('p', { class: 'text-secondary mb-0', text: 'Todavía no tiene oportunidades cerradas.' })
                    : h('div', { class: 'list-group' }, cerradas.map(tarjeta)))));
}

function tarjeta(oportunidad) {
    return h('button', {
        class: 'list-group-item list-group-item-action text-start opportunity-card',
        type: 'button', dataset: { action: 'abrir-oportunidad', id: String(oportunidad.id) },
    },
    h('span', { class: 'd-block fw-semibold text-break', text: oportunidad.titulo }),
    h('span', { class: 'd-block small text-secondary text-break', text: oportunidad.cliente?.nombre ?? 'Punto de venta' }),
    h('span', { class: 'd-block small mt-2', text: `Total: Q${Number(oportunidad.total ?? 0).toFixed(2)} · ${oportunidad.probabilidad}%` }));
}
