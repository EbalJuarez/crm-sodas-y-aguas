import { MAXIMO_CARTERA } from '../config.js';
import { alerta, h, spinner } from '../util/dom.js';

/**
 * F2.1, F2.2: «Mi cartera» con búsqueda en el servidor, contador «x de 10» y alta de punto de
 * venta. Las reglas (NIT, teléfono, repetidos, cartera llena) las decide el servidor: aquí solo se
 * muestran sus mensajes junto a cada campo, conservando lo escrito.
 */
export function crearVistaCartera({ cartera, alta }) {
    const llena = cartera.total !== null && cartera.total >= MAXIMO_CARTERA;

    return h('section', { class: 'vstack gap-3' },
        h('div', { class: 'd-flex flex-wrap align-items-center justify-content-between gap-2' },
            h('h2', { class: 'h4 mb-0' }, 'Mi cartera ',
                h('span', {
                    class: `badge ${llena ? 'text-bg-warning' : 'text-bg-secondary'} align-middle`,
                    dataset: { contador: 'cartera' },
                    text: `${cartera.total ?? '…'} de ${MAXIMO_CARTERA}`,
                })),
            !alta.abierta && h('button', {
                class: 'btn btn-agua btn-sm',
                type: 'button',
                dataset: { action: 'abrir-alta' },
            }, 'Nuevo punto de venta')),
        alta.abierta && formularioAlta(alta),
        h('div', { role: 'search' },
            h('label', { class: 'form-label visually-hidden', htmlFor: 'busqueda', text: 'Buscar por nombre o NIT' }),
            h('input', {
                class: 'form-control',
                id: 'busqueda',
                name: 'q',
                type: 'search',
                value: cartera.q,
                placeholder: 'Buscar por nombre o NIT',
                autocomplete: 'off',
            })),
        cartera.error && alerta('danger', cartera.error),
        lista(cartera));
}

function lista(cartera) {
    if (!cartera.cargada) {
        return h('div', { class: 'text-secondary py-3' }, spinner('Cargando su cartera…'));
    }

    if (cartera.clientes.length === 0) {
        return h('p', {
            class: 'text-secondary py-3 mb-0 text-break',
            text: cartera.q.trim() === ''
                ? 'Todavía no tiene puntos de venta en su cartera. Tome uno en «Disponibles» o agregue uno nuevo.'
                : `No hay puntos de venta que coincidan con «${cartera.q.trim()}».`,
        });
    }

    return h('ul', { class: 'list-group shadow-sm', 'aria-busy': cartera.cargando ? 'true' : null },
        cartera.clientes.map((cliente) => h('li', { class: 'list-group-item py-3' },
            h('p', { class: 'fw-semibold mb-1 text-break', text: cliente.nombre }),
            h('p', { class: 'small text-secondary mb-0 text-break' },
                `NIT: ${cliente.nit ?? 'CF'} · Tel. ${cliente.telefono}`,
                cliente.correo ? ` · ${cliente.correo}` : ''))));
}

function formularioAlta(alta) {
    const { datos, errores, error, enviando } = alta;

    return h('form', { class: 'card card-body shadow-sm vstack gap-3', dataset: { form: 'alta' }, noValidate: true },
        h('h3', { class: 'h5 mb-0', text: 'Nuevo punto de venta' }),
        campo('nombre', 'Nombre', datos.nombre, errores.nombre, { autocomplete: 'organization' }),
        h('div', { class: 'row g-3' },
            h('div', { class: 'col-12 col-sm-6' },
                campo('nit', 'NIT (o CF)', datos.nit, errores.nit, { autocomplete: 'off' })),
            h('div', { class: 'col-12 col-sm-6' },
                campo('telefono', 'Teléfono', datos.telefono, errores.telefono, { type: 'tel', autocomplete: 'tel' }))),
        campo('correo', 'Correo (opcional)', datos.correo, errores.correo, { type: 'email', autocomplete: 'email' }),
        error && alerta('danger', error),
        h('div', { class: 'd-flex flex-wrap gap-2' },
            h('button', { class: 'btn btn-agua', type: 'submit', disabled: enviando },
                enviando ? 'Guardando…' : 'Guardar'),
            h('button', { class: 'btn btn-outline-secondary', type: 'button', disabled: enviando, dataset: { action: 'cancelar-alta' } },
                'Cancelar')));
}

function campo(nombre, etiqueta, valor, mensaje, { type = 'text', autocomplete } = {}) {
    const id = `alta-${nombre}`;

    return h('div', {},
        h('label', { class: 'form-label', htmlFor: id, text: etiqueta }),
        h('input', {
            class: `form-control${mensaje ? ' is-invalid' : ''}`,
            id,
            name: nombre,
            type,
            value: valor,
            autocomplete,
            'aria-invalid': mensaje ? 'true' : null,
            'aria-describedby': mensaje ? `${id}-error` : null,
        }),
        mensaje && h('div', { class: 'invalid-feedback', id: `${id}-error`, text: mensaje }));
}
