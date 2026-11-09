import { ORIGEN_SPA } from '../config.js';
import { alerta, h, spinner } from '../util/dom.js';

/** Encabezado, pestañas y avisos comunes de la sesión, con el contenido de la vista. */
export function crearLayout(estado, contenido) {
    return h('div', { class: 'min-vh-100 d-flex flex-column' },
        h('header', { class: 'home-header py-3' },
            h('div', { class: 'container d-flex justify-content-between align-items-center gap-2' },
                h('h1', { class: 'h5 mb-0 text-truncate', text: 'Distribuidora Aguaviva' }),
                h('div', { class: 'd-flex align-items-center gap-3' },
                    estado.pendientes > 0 && h('span', { class: 'small' }, spinner()),
                    h('button', {
                        class: 'btn btn-outline-light btn-sm text-nowrap',
                        type: 'button',
                        dataset: { action: 'logout' },
                    }, 'Cerrar sesión')))),
        estado.vista !== 'verificando' && pestanas(estado.vista),
        h('main', { class: 'container py-4 flex-grow-1' },
            avisoDeOrigen(),
            estado.conexion && alerta('danger', estado.conexion,
                h('button', { class: 'btn btn-sm btn-outline-danger', type: 'button', dataset: { action: 'reintentar' } }, 'Reintentar')),
            estado.aviso && alerta('success', estado.aviso,
                h('button', { class: 'btn-close', type: 'button', 'aria-label': 'Cerrar aviso', dataset: { action: 'cerrar-aviso' } })),
            contenido));
}

function pestanas(vista) {
    const pestana = (destino, texto) => h('li', { class: 'nav-item' },
        h('a', {
            class: `nav-link${(vista === destino || (destino === 'embudo' && vista === 'detalle')) ? ' active' : ''}`,
            href: `#/${destino}`,
            'aria-current': vista === destino ? 'page' : null,
        }, texto));

    return h('nav', { class: 'bg-white border-bottom', 'aria-label': 'Secciones' },
        h('div', { class: 'container' },
            h('ul', { class: 'nav nav-underline' },
                pestana('cartera', 'Mi cartera'),
                pestana('disponibles', 'Disponibles'),
                pestana('embudo', 'Pedidos por cerrar'))));
}

/** Con otro origen (p. ej. localhost:5500) CORS bloquea todas las peticiones: se explica aquí. */
export function avisoDeOrigen() {
    if (window.location.origin === ORIGEN_SPA) {
        return null;
    }

    return alerta('warning',
        `Abra esta aplicación desde ${ORIGEN_SPA}. La API solo acepta peticiones de ese origen (CORS_ORIGIN en api/.env); desde ${window.location.origin} el navegador las bloquea.`);
}

export function crearVistaVerificando(estado) {
    return crearLayout(estado,
        estado.conexion ? null : h('div', { class: 'text-center py-5 text-secondary' }, spinner('Verificando su sesión…')));
}
