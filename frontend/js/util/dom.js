/**
 * h('tag', props, ...hijos): crea elementos sin innerHTML (S7). Todo texto se agrega como nodo de
 * texto, así que un nombre como <img src=x onerror=alert(1)> se ve tal cual y no se ejecuta.
 *
 * props: class, dataset, text, y propiedades booleanas (disabled, hidden, required, …); el resto
 * se asigna con setAttribute, que tampoco interpreta HTML.
 */
const PROPIEDADES = new Set(['disabled', 'hidden', 'required', 'checked', 'value', 'noValidate', 'htmlFor', 'type', 'name', 'id']);

export function h(tag, props = {}, ...hijos) {
    const elemento = document.createElement(tag);

    for (const [clave, valor] of Object.entries(props ?? {})) {
        if (valor === undefined || valor === null || valor === false) {
            continue;
        }
        if (clave === 'class') {
            elemento.className = valor;
        } else if (clave === 'dataset') {
            Object.assign(elemento.dataset, valor);
        } else if (clave === 'text') {
            elemento.textContent = String(valor);
        } else if (PROPIEDADES.has(clave)) {
            elemento[clave] = valor;
        } else {
            elemento.setAttribute(clave, String(valor));
        }
    }

    agregar(elemento, hijos);
    return elemento;
}

function agregar(elemento, hijos) {
    for (const hijo of hijos) {
        if (hijo === null || hijo === undefined || hijo === false) {
            continue;
        }
        if (Array.isArray(hijo)) {
            agregar(elemento, hijo);
        } else if (hijo instanceof Node) {
            elemento.append(hijo);
        } else {
            elemento.append(document.createTextNode(String(hijo)));
        }
    }
}

export function spinner(texto = 'Cargando…') {
    return h('span', { class: 'd-inline-flex align-items-center gap-2', role: 'status' },
        h('span', { class: 'spinner-border spinner-border-sm', 'aria-hidden': 'true' }),
        h('span', { text: texto }));
}

export function alerta(tipo, texto, accion = null) {
    return h('div', { class: `alert alert-${tipo} d-flex flex-wrap align-items-center justify-content-between gap-2`, role: 'alert' },
        h('span', { class: 'text-break', text: texto }),
        accion);
}
