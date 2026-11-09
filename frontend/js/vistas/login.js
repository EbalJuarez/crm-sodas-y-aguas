import { alerta, h } from '../util/dom.js';
import { avisoDeOrigen } from './layout.js';

/** F1: inicio de sesión. Conserva el correo escrito después de un error. */
export function crearVistaLogin({ login, conexion }) {
    const { correo, errores, error, aviso, enviando } = login;

    return h('main', { class: 'login-page d-flex align-items-center py-5' },
        h('div', { class: 'container' },
            h('div', { class: 'row justify-content-center' },
                h('div', { class: 'col-12 col-sm-10 col-md-8 col-lg-5' },
                    h('div', { class: 'card login-card rounded-4' },
                        h('div', { class: 'card-body p-4 p-sm-5' },
                            h('div', { class: 'd-flex align-items-center gap-3 mb-4' },
                                h('span', { class: 'brand-mark', text: 'A', 'aria-hidden': 'true' }),
                                h('div', {},
                                    h('p', { class: 'text-uppercase small fw-semibold text-secondary mb-1', text: 'Distribuidora' }),
                                    h('h1', { class: 'h4 brand-name mb-0', text: 'Aguaviva' }))),
                            h('p', { class: 'text-secondary mb-4', text: 'Ingrese para gestionar sus ventas y puntos de venta.' }),
                            avisoDeOrigen(),
                            aviso && alerta('warning', aviso),
                            h('form', { class: 'vstack gap-3', dataset: { form: 'login' }, noValidate: true },
                                campo('correo', 'Correo electrónico', 'email', correo, errores.correo, 'email'),
                                campo('clave', 'Contraseña', 'password', '', errores.clave, 'current-password'),
                                (error || conexion) && alerta('danger', error ?? conexion),
                                h('button', {
                                    class: 'btn btn-agua w-100 py-2 mt-2',
                                    type: 'submit',
                                    disabled: enviando,
                                }, enviando ? 'Ingresando…' : 'Iniciar sesión'))))))));
}

function campo(nombre, etiqueta, tipo, valor, mensaje, autocompletar) {
    const id = `login-${nombre}`;

    return h('div', {},
        h('label', { class: 'form-label fw-semibold', htmlFor: id, text: etiqueta }),
        h('input', {
            class: `form-control form-control-lg${mensaje ? ' is-invalid' : ''}`,
            id,
            name: nombre,
            type: tipo,
            value: valor,
            autocomplete: autocompletar,
            required: true,
            'aria-invalid': mensaje ? 'true' : null,
            'aria-describedby': mensaje ? `${id}-error` : null,
        }),
        mensaje && h('div', { class: 'invalid-feedback', id: `${id}-error`, text: mensaje }));
}
