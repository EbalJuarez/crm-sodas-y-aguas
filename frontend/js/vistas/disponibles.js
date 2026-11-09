import { alerta, h, spinner } from '../util/dom.js';

/** F2.3–F2.5: puntos de venta disponibles (solo id y nombre) con la acción de tomarlos. */
export function crearVistaDisponibles({ disponibles }) {
    const { lista, cargada, mensaje, tomando } = disponibles;

    return h('section', { class: 'vstack gap-3' },
        h('h2', { class: 'h4 mb-0', text: 'Disponibles' }),
        h('p', { class: 'text-secondary mb-0', text: 'Puntos de venta sin vendedor. Al tomar uno pasa a su cartera.' }),
        mensaje && alerta('warning', mensaje),
        !cargada
            ? h('div', { class: 'text-secondary py-3' }, spinner('Cargando disponibles…'))
            : lista.length === 0
                ? h('p', { class: 'text-secondary py-3 mb-0', text: 'No hay puntos de venta disponibles en este momento.' })
                : h('ul', { class: 'list-group shadow-sm' },
                    lista.map((cliente) => h('li', { class: 'list-group-item d-flex align-items-center justify-content-between gap-3 py-3' },
                        h('span', { class: 'fw-semibold text-break', text: cliente.nombre }),
                        h('button', {
                            class: 'btn btn-agua btn-sm flex-shrink-0',
                            type: 'button',
                            disabled: tomando !== null,
                            dataset: { action: 'tomar', id: String(cliente.id) },
                            'aria-label': `Tomar ${cliente.nombre}`,
                        }, tomando === cliente.id ? 'Tomando…' : 'Tomar')))));
}
