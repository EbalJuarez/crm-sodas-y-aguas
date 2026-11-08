const TOKEN_KEY = 'crm_agua_token';

let estado = {
    token: localStorage.getItem(TOKEN_KEY),
    vista: localStorage.getItem(TOKEN_KEY) ? 'inicio' : 'login',
    cargando: false,
    error: null,
    erroresCampo: {},
};

const suscriptores = new Set();

export function obtenerEstado() {
    return { ...estado, erroresCampo: { ...estado.erroresCampo } };
}

export function suscribirse(render) {
    suscriptores.add(render);
    render(obtenerEstado());
    return () => suscriptores.delete(render);
}

function actualizar(cambios) {
    estado = { ...estado, ...cambios };
    const copia = obtenerEstado();
    suscriptores.forEach((render) => render(copia));
}

export function iniciarPeticion() {
    actualizar({ cargando: true, error: null, erroresCampo: {} });
}

export function loginCorrecto(token) {
    localStorage.setItem(TOKEN_KEY, token);
    actualizar({ token, vista: 'inicio', cargando: false, error: null, erroresCampo: {} });
}

export function loginFallido({ error = null, erroresCampo = {} } = {}) {
    actualizar({ cargando: false, error, erroresCampo });
}

export function cerrarSesion(mensaje = null) {
    localStorage.removeItem(TOKEN_KEY);
    actualizar({ token: null, vista: 'login', cargando: false, error: mensaje, erroresCampo: {} });
}

export function sesionNoAutorizada() {
    cerrarSesion('Su sesión ya no es válida. Inicie sesión nuevamente.');
}

export function limpiarMensajes() {
    actualizar({ error: null, erroresCampo: {} });
}
