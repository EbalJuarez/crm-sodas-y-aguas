import { alerta, h, spinner } from '../util/dom.js';
import { formatearGuatemala } from '../util/fechas.js';

const TRANSICIONES = {
    prospecto: ['calificado', 'perdida'],
    calificado: ['propuesta', 'perdida'],
    propuesta: ['negociacion', 'perdida'],
    negociacion: ['ganada', 'perdida'],
};
const ETIQUETAS = {
    calificado: 'Calificado', propuesta: 'Propuesta', negociacion: 'Negociación',
    ganada: 'Ganada', perdida: 'Perdida', prospecto: 'Prospecto',
};

export function crearVistaDetalle({ detalle }) {
    if (detalle.cargando) {
        return h('div', { class: 'py-4 text-secondary' }, spinner('Cargando oportunidad…'));
    }
    if (detalle.error) {
        return h('section', { class: 'vstack gap-3' },
            alerta('danger', detalle.error),
            h('button', { class: 'btn btn-outline-secondary align-self-start', type: 'button', dataset: { action: 'volver-embudo' } }, 'Volver al embudo'));
    }
    if (!detalle.datos) {
        return h('p', { class: 'text-secondary', text: 'No se encontró la oportunidad.' });
    }

    const oportunidad = detalle.datos;
    const cerrada = ['ganada', 'perdida'].includes(oportunidad.etapa);
    return h('section', { class: 'vstack gap-3' },
        h('button', { class: 'btn btn-link link-secondary p-0 align-self-start text-decoration-none', type: 'button', dataset: { action: 'volver-embudo' } }, '← Volver al embudo'),
        h('div', { class: 'd-flex flex-wrap justify-content-between align-items-start gap-3' },
            h('div', {},
                h('p', { class: 'text-uppercase small text-secondary mb-1', text: 'Pedido por cerrar' }),
                h('h2', { class: 'h4 mb-1 text-break', text: oportunidad.titulo }),
                h('p', { class: 'mb-0 text-secondary text-break', text: oportunidad.cliente?.nombre ?? '' })),
            h('span', { class: 'badge text-bg-primary fs-6', text: `${ETIQUETAS[oportunidad.etapa]} · ${oportunidad.probabilidad}%` })),
        !cerrada && formularioTitulo(detalle),
        h('div', { class: 'row g-3' },
            h('div', { class: 'col-12 col-xl-7' }, productos(oportunidad, detalle, cerrada)),
            h('div', { class: 'col-12 col-xl-5' }, historial(oportunidad), !cerrada && formularioEtapa(detalle, oportunidad.etapa))),
        oportunidad.fecha_cierre && h('p', { class: 'small text-secondary mb-0', text: `Fecha de cierre: ${formatearGuatemala(oportunidad.fecha_cierre)}` }));
}

function formularioTitulo(detalle) {
    return h('form', { class: 'card card-body home-panel vstack gap-2', dataset: { form: 'editar-titulo' }, noValidate: true },
        h('label', { class: 'form-label mb-0', htmlFor: 'titulo-oportunidad', text: 'Título' }),
        h('div', { class: 'input-group' },
            h('input', {
                class: `form-control${detalle.tituloErrores.titulo ? ' is-invalid' : ''}`,
                id: 'titulo-oportunidad', name: 'titulo', value: detalle.titulo,
                'aria-invalid': detalle.tituloErrores.titulo ? 'true' : null,
            }),
            h('button', { class: 'btn btn-outline-primary', type: 'submit', disabled: detalle.guardandoTitulo }, detalle.guardandoTitulo ? 'Guardando…' : 'Guardar')),
        detalle.tituloErrores.titulo && h('div', { class: 'invalid-feedback d-block', text: detalle.tituloErrores.titulo }),
        detalle.tituloErrores._ && alerta('danger', detalle.tituloErrores._));
}

function productos(oportunidad, detalle, cerrada) {
    return h('section', { class: 'card home-panel' },
        h('div', { class: 'card-body' },
            h('h3', { class: 'h5', text: 'Productos' }),
            oportunidad.productos.length === 0
                ? h('p', { class: 'text-secondary', text: 'No hay productos agregados. Se necesita al menos uno para ganar.' })
                : h('div', { class: 'table-responsive' },
                    h('table', { class: 'table align-middle mb-3' },
                        h('thead', {}, h('tr', {},
                            h('th', { scope: 'col', text: 'Producto' }), h('th', { scope: 'col', text: 'Cantidad' }),
                            h('th', { scope: 'col', text: 'Precio' }), h('th', { scope: 'col', text: 'Subtotal' }),
                            !cerrada && h('th', { scope: 'col', text: 'Acciones' }))),
                        h('tbody', {}, oportunidad.productos.map((linea) => filaProducto(linea, detalle, cerrada))))),
            h('div', { class: 'd-flex justify-content-end border-top pt-3' },
                h('strong', { text: `Total: Q${Number(oportunidad.total ?? 0).toFixed(2)}` })),
            !cerrada && formularioProducto(detalle)));
}

function filaProducto(linea, detalle, cerrada) {
    return h('tr', {},
        h('td', { class: 'text-break', text: linea.nombre }),
        h('td', {},
            !cerrada
                ? h('div', { class: 'input-group input-group-sm quantity-control' },
                    h('input', { class: 'form-control', id: `cantidad-${linea.id}`, type: 'number', min: '1', max: '999', value: String(linea.cantidad), 'aria-label': `Cantidad de ${linea.nombre}` }),
                    h('button', { class: 'btn btn-outline-primary', type: 'button', dataset: { action: 'cambiar-cantidad', id: String(linea.id) } }, 'Guardar'))
                : String(linea.cantidad)),
        h('td', { class: 'text-nowrap', text: `Q${Number(linea.precio_unitario).toFixed(2)}` }),
        h('td', { class: 'text-nowrap', text: `Q${Number(linea.subtotal).toFixed(2)}` }),
        !cerrada && h('td', {}, h('button', { class: 'btn btn-outline-danger btn-sm', type: 'button', dataset: { action: 'eliminar-producto', id: String(linea.id) } }, 'Quitar')));
}

function formularioProducto(detalle) {
    const producto = detalle.producto;
    return h('form', { class: 'border-top pt-3 mt-3 vstack gap-2', dataset: { form: 'agregar-producto' }, noValidate: true },
        h('h4', { class: 'h6', text: 'Agregar producto' }),
        h('div', { class: 'row g-2' },
            h('div', { class: 'col-12 col-md-7' },
                h('label', { class: 'form-label visually-hidden', htmlFor: 'producto-nuevo', text: 'Producto' }),
                h('select', { class: `form-select${producto.errores.producto_id ? ' is-invalid' : ''}`, id: 'producto-nuevo', name: 'producto_id', value: producto.producto_id },
                    h('option', { value: '' }, 'Elija un producto'),
                    detalle.catalogo.map((item) => h('option', { value: String(item.id) }, `${item.nombre} · Q${Number(item.precio).toFixed(2)}`)))),
            h('div', { class: 'col-8 col-md-3' },
                h('label', { class: 'form-label visually-hidden', htmlFor: 'cantidad-nueva', text: 'Cantidad' }),
                h('input', { class: `form-control${producto.errores.cantidad ? ' is-invalid' : ''}`, id: 'cantidad-nueva', name: 'cantidad', type: 'number', min: '1', max: '999', value: producto.cantidad, placeholder: 'Cantidad' })),
            h('div', { class: 'col-4 col-md-2 d-grid' },
                h('button', { class: 'btn btn-agua', type: 'submit', disabled: producto.enviando }, producto.enviando ? '…' : 'Agregar'))),
        Object.entries(producto.errores).map(([campo, mensaje]) => h('div', { class: 'invalid-feedback d-block', text: mensaje })),
        producto.error && alerta('danger', producto.error));
}

function historial(oportunidad) {
    return h('section', { class: 'card home-panel mb-3' },
        h('div', { class: 'card-body' },
            h('h3', { class: 'h5', text: 'Historial de etapas' }),
            h('ol', { class: 'list-group list-group-numbered' },
                oportunidad.historial.map((registro) => h('li', { class: 'list-group-item d-flex justify-content-between gap-2' },
                    h('span', { class: 'text-break', text: `${registro.etapa_anterior ? ETIQUETAS[registro.etapa_anterior] : 'Creación'} → ${ETIQUETAS[registro.etapa_nueva]}` }),
                    h('time', { class: 'small text-secondary text-nowrap', dateTime: registro.fecha, text: formatearGuatemala(registro.fecha) }))))));
}

function formularioEtapa(detalle, etapaActual) {
    const opciones = TRANSICIONES[etapaActual] ?? [];
    return h('form', { class: 'card home-panel', dataset: { form: 'cambiar-etapa' }, noValidate: true },
        h('div', { class: 'card-body vstack gap-2' },
            h('h3', { class: 'h5', text: 'Cambiar etapa' }),
            h('select', { class: `form-select${detalle.etapa.errores.etapa ? ' is-invalid' : ''}`, id: 'etapa-nueva', name: 'etapa', value: detalle.etapa.etapa },
                h('option', { value: '' }, 'Elija una transición'),
                opciones.map((opcion) => h('option', { value: opcion }, ETIQUETAS[opcion]))),
            detalle.etapa.etapa === 'perdida' && h('textarea', { class: `form-control${detalle.etapa.errores.motivo_perdida ? ' is-invalid' : ''}`, id: 'motivo-perdida', name: 'motivo_perdida', rows: '2', placeholder: 'Motivo de la pérdida', text: detalle.etapa.motivo }),
            Object.values(detalle.etapa.errores).map((mensaje) => h('div', { class: 'invalid-feedback d-block', text: mensaje })),
            detalle.etapa.error && alerta('danger', detalle.etapa.error),
            h('button', { class: 'btn btn-outline-primary align-self-start', type: 'submit', disabled: detalle.etapa.enviando }, detalle.etapa.enviando ? 'Guardando…' : 'Cambiar')));
}
