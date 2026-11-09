import { alerta, h, spinner } from '../util/dom.js';
import { formatearGuatemala } from '../util/fechas.js';

const TIPOS = [
    ['llamada', 'Llamada'],
    ['reunion', 'Reunión'],
    ['tarea', 'Tarea'],
];

export function crearVistaActividades({ actividades, cartera }) {
    const pendientes = actividades.lista.filter((actividad) => !actividad.completada);
    const completadas = actividades.lista.filter((actividad) => actividad.completada);

    return h('section', { class: 'vstack gap-3' },
        h('div', { class: 'd-flex flex-wrap align-items-center justify-content-between gap-2' },
            h('div', {},
                h('h2', { class: 'h4 mb-1', text: 'Actividades' }),
                h('p', { class: 'text-secondary mb-0', text: 'Dé seguimiento a sus puntos de venta.' })),
            !actividades.nueva.abierta && h('button', {
                class: 'btn btn-agua btn-sm', type: 'button', dataset: { action: 'abrir-nueva-actividad' },
            }, 'Nueva actividad')),
        h('div', { class: 'row g-2 align-items-end' },
            h('div', { class: 'col-12 col-sm-6 col-md-4' },
                h('label', { class: 'form-label', htmlFor: 'filtro-actividades', text: 'Mostrar' }),
                h('select', { class: 'form-select', id: 'filtro-actividades', name: 'filtro-actividades', value: actividades.filtro },
                    h('option', { value: '' }, 'Pendientes y completadas'),
                    h('option', { value: 'pendiente' }, 'Solo pendientes'),
                    h('option', { value: 'completada' }, 'Solo completadas')))),
        actividades.nueva.abierta && formularioNueva(actividades.nueva, cartera.clientes),
        actividades.error && alerta('danger', actividades.error),
        !actividades.cargada
            ? h('div', { class: 'text-secondary py-4' }, spinner('Cargando actividades…'))
            : h('div', { class: 'row g-4' },
                h('div', { class: 'col-12 col-lg-6' },
                    h('h3', { class: 'h5', text: `Pendientes (${pendientes.length})` }),
                    lista(pendientes, actividades)),
                h('div', { class: 'col-12 col-lg-6' },
                    h('h3', { class: 'h5', text: `Completadas (${completadas.length})` }),
                    lista(completadas, actividades))));
}

function formularioNueva(nueva, clientes) {
    return h('form', { class: 'card card-body home-panel vstack gap-3', dataset: { form: 'crear-actividad' }, noValidate: true },
        h('h3', { class: 'h5 mb-0', text: 'Nueva actividad' }),
        campoSelect('actividad-cliente', 'Punto de venta', 'cliente_id', nueva.datos.cliente_id, clientes.map((cliente) => [String(cliente.id), cliente.nombre]), nueva.errores.cliente_id, 'Elija un punto de venta'),
        campoSelect('actividad-tipo', 'Tipo', 'tipo', nueva.datos.tipo, TIPOS, nueva.errores.tipo),
        campoTexto('actividad-descripcion', 'Descripción', 'descripcion', nueva.datos.descripcion, nueva.errores.descripcion),
        h('div', {},
            h('label', { class: 'form-label', htmlFor: 'actividad-vence', text: 'Vencimiento (hora de Guatemala, opcional)' }),
            h('input', { class: `form-control${nueva.errores.vence_en ? ' is-invalid' : ''}`, id: 'actividad-vence', name: 'vence_en', type: 'datetime-local', value: nueva.datos.vence_en, 'aria-invalid': nueva.errores.vence_en ? 'true' : null }),
            nueva.errores.vence_en && h('div', { class: 'invalid-feedback', text: nueva.errores.vence_en })),
        nueva.error && alerta('danger', nueva.error),
        h('div', { class: 'd-flex flex-wrap gap-2' },
            h('button', { class: 'btn btn-agua', type: 'submit', disabled: nueva.enviando }, nueva.enviando ? 'Guardando…' : 'Guardar'),
            h('button', { class: 'btn btn-outline-secondary', type: 'button', disabled: nueva.enviando, dataset: { action: 'cancelar-nueva-actividad' } }, 'Cancelar')));
}

function campoSelect(id, etiqueta, nombre, valor, opciones, error, placeholder = null) {
    return h('div', {},
        h('label', { class: 'form-label', htmlFor: id, text: etiqueta }),
        h('select', { class: `form-select${error ? ' is-invalid' : ''}`, id, name, value: valor },
            placeholder && h('option', { value: '' }, placeholder),
            opciones.map(([clave, texto]) => h('option', { value: clave }, texto))),
        error && h('div', { class: 'invalid-feedback', text: error }));
}

function campoTexto(id, etiqueta, nombre, valor, error) {
    return h('div', {},
        h('label', { class: 'form-label', htmlFor: id, text: etiqueta }),
        h('textarea', { class: `form-control${error ? ' is-invalid' : ''}`, id, name, rows: '2', text: valor }),
        error && h('div', { class: 'invalid-feedback', text: error }));
}

function lista(listaActividades, estado) {
    if (listaActividades.length === 0) {
        return h('p', { class: 'text-secondary', text: 'No hay actividades en esta sección.' });
    }

    return h('div', { class: 'vstack gap-2' }, listaActividades.map((actividad) => tarjeta(actividad, estado)));
}

function tarjeta(actividad, estado) {
    const vencida = actividad.vencida && !actividad.completada;
    return h('article', { class: `card home-panel activity-card${vencida ? ' activity-overdue' : ''}` },
        h('div', { class: 'card-body' },
            h('div', { class: 'd-flex justify-content-between align-items-start gap-2' },
                h('div', {},
                    h('span', { class: 'badge text-bg-light mb-2', text: etiquetaTipo(actividad.tipo) }),
                    vencida && h('span', { class: 'badge text-bg-danger ms-1 mb-2', text: 'Vencida' }),
                    h('h4', { class: 'h6 mb-1 text-break', text: actividad.descripcion }),
                    h('p', { class: 'small text-secondary mb-2 text-break', text: actividad.cliente.nombre })),
                h('button', {
                    class: 'btn btn-outline-primary btn-sm text-nowrap', type: 'button',
                    disabled: estado.actualizando === actividad.id,
                    dataset: { action: 'cambiar-actividad', id: String(actividad.id), completada: String(!actividad.completada) },
                }, estado.actualizando === actividad.id ? 'Guardando…' : (actividad.completada ? 'Reabrir' : 'Completar'))),
            h('p', { class: 'small mb-0', text: actividad.vence_en ? `Vence: ${formatearGuatemala(actividad.vence_en)}` : 'Sin fecha de vencimiento' })));
}

function etiquetaTipo(tipo) {
    return TIPOS.find(([clave]) => clave === tipo)?.[1] ?? tipo;
}
