import { ApiError, iniciarSesion } from './api.js';
import {
    cerrarSesion,
    iniciarPeticion,
    loginCorrecto,
    loginFallido,
    suscribirse,
} from './estado.js';
import { crearVistaLogin } from './vistas/login.js';

const app = document.querySelector('#app');

function render(estado) {
    app.replaceChildren(estado.vista === 'login' ? crearVistaLogin(estado) : crearVistaInicio());
}

function crearVistaInicio() {
    const page = document.createElement('section');
    page.className = 'min-vh-100';

    const header = document.createElement('header');
    header.className = 'home-header py-3';
    const headerContainer = document.createElement('div');
    headerContainer.className = 'container d-flex justify-content-between align-items-center gap-3';
    const title = document.createElement('h1');
    title.className = 'h5 mb-0';
    title.textContent = 'Distribuidora Aguaviva';
    const logout = document.createElement('button');
    logout.className = 'btn btn-outline-light btn-sm';
    logout.type = 'button';
    logout.dataset.action = 'logout';
    logout.textContent = 'Cerrar sesión';
    headerContainer.append(title, logout);
    header.append(headerContainer);

    const main = document.createElement('main');
    main.className = 'container py-5';
    const panel = document.createElement('div');
    panel.className = 'card home-panel rounded-4';
    const body = document.createElement('div');
    body.className = 'card-body p-4 p-md-5';
    const heading = document.createElement('h2');
    heading.className = 'h3';
    heading.textContent = 'Su sesión está activa';
    const message = document.createElement('p');
    message.className = 'text-secondary mb-0';
    message.textContent = 'Aquí podrá consultar su cartera y sus pedidos por cerrar.';
    body.append(heading, message);
    panel.append(body);
    main.append(panel);
    page.append(header, main);
    return page;
}

async function procesarLogin(form) {
    const datos = new FormData(form);
    const correo = String(datos.get('correo') ?? '').trim();
    const clave = String(datos.get('clave') ?? '');

    iniciarPeticion();
    try {
        const respuesta = await iniciarSesion(correo, clave);
        loginCorrecto(respuesta.token);
    } catch (error) {
        if (error instanceof ApiError) {
            if (error.status === 422) {
                loginFallido({ erroresCampo: error.payload?.errores ?? {}, error: 'Revise los datos ingresados.' });
            } else if (error.status === 403) {
                loginFallido({ error: error.payload?.error ?? 'Usted no tiene acceso a la aplicación de vendedores.' });
            } else if (error.status === 401) {
                loginFallido({ error: error.payload?.error ?? 'El correo o la clave son incorrectos.' });
            } else {
                loginFallido({ error: 'La API no pudo completar la solicitud. Inténtelo nuevamente.' });
            }
        } else {
            loginFallido({ error: error.message });
        }
    }
}

app.addEventListener('submit', (event) => {
    if (!event.target.matches('[data-login-form]')) {
        return;
    }
    event.preventDefault();
    procesarLogin(event.target);
});

app.addEventListener('click', (event) => {
    const action = event.target.closest('[data-action]')?.dataset.action;
    if (action === 'logout') {
        cerrarSesion();
    }
});

suscribirse(render);
