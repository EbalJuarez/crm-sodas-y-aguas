function crearElemento(tag, clases = [], texto = null) {
    const elemento = document.createElement(tag);
    elemento.classList.add(...clases);
    if (texto !== null) {
        elemento.textContent = texto;
    }
    return elemento;
}

export function crearVistaLogin({ cargando, error, erroresCampo }) {
    const page = crearElemento('section', ['login-page', 'd-flex', 'align-items-center', 'py-5']);
    const container = crearElemento('div', ['container']);
    const row = crearElemento('div', ['row', 'justify-content-center']);
    const column = crearElemento('div', ['col-12', 'col-sm-10', 'col-md-8', 'col-lg-5']);
    const card = crearElemento('div', ['card', 'login-card', 'rounded-4']);
    const body = crearElemento('div', ['card-body', 'p-4', 'p-sm-5']);
    const heading = crearElemento('div', ['d-flex', 'align-items-center', 'gap-3', 'mb-4']);
    const mark = crearElemento('span', ['brand-mark'], 'A');
    const brand = crearElemento('div');
    brand.append(
        crearElemento('p', ['text-uppercase', 'small', 'fw-semibold', 'text-secondary', 'mb-1'], 'Distribuidora'),
        crearElemento('h1', ['h4', 'brand-name', 'mb-0'], 'Aguaviva'),
    );
    heading.append(mark, brand);

    const intro = crearElemento('p', ['text-secondary', 'mb-4'], 'Ingrese para gestionar sus ventas y clientes.');
    const form = crearElemento('form', ['vstack', 'gap-3']);
    form.dataset.loginForm = 'true';

    const correoGroup = crearCampo('correo', 'Correo electrónico', 'email', erroresCampo.correo);
    const claveGroup = crearCampo('clave', 'Contraseña', 'password', erroresCampo.clave);
    form.append(correoGroup.group, claveGroup.group);

    if (error) {
        const alert = crearElemento('div', ['alert', 'alert-danger', 'mb-0'], error);
        alert.setAttribute('role', 'alert');
        form.append(alert);
    }

    const button = crearElemento('button', ['btn', 'btn-agua', 'w-100', 'py-2', 'mt-2'], cargando ? 'Ingresando...' : 'Iniciar sesión');
    button.type = 'submit';
    button.disabled = cargando;
    form.append(button);

    body.append(heading, intro, form);
    card.append(body);
    column.append(card);
    row.append(column);
    container.append(row);
    page.append(container);
    return page;
}

function crearCampo(nombre, etiqueta, tipo, mensaje) {
    const group = crearElemento('div');
    const label = crearElemento('label', ['form-label', 'fw-semibold'], etiqueta);
    label.htmlFor = nombre;
    const input = crearElemento('input', ['form-control', 'form-control-lg']);
    input.id = nombre;
    input.name = nombre;
    input.type = tipo;
    input.autocomplete = nombre === 'clave' ? 'current-password' : 'email';
    input.required = true;
    if (mensaje) {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
    }
    const feedback = crearElemento('div', ['invalid-feedback'], mensaje ?? '');
    feedback.hidden = !mensaje;
    group.append(label, input, feedback);
    return { group };
}
