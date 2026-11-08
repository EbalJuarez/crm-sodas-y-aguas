import {
    ApiError,
    ConexionError,
    configurarApi,
    crearCliente,
    iniciarSesion,
    listarCartera,
    listarDisponibles,
    tomarCliente,
} from './api.js';
import { ESPERA_BUSQUEDA_MS } from './config.js';
import {
    actualizar,
    altaVacia,
    borrarToken,
    estadoInicial,
    guardarToken,
    obtenerEstado,
    suscribirse,
} from './estado.js';
import { crearVistaCartera } from './vistas/cartera.js';
import { crearVistaDisponibles } from './vistas/disponibles.js';
import { crearLayout, crearVistaVerificando } from './vistas/layout.js';
import { crearVistaLogin } from './vistas/login.js';

const app = document.querySelector('#app');
const MENSAJE_SESION_VENCIDA = 'Su sesión venció o su cuenta ya no tiene acceso. Inicie sesión nuevamente.';
const MENSAJE_GENERICO = 'La API no pudo completar la solicitud. Inténtelo nuevamente.';

// Cada inicio o cierre de sesión cambia este número: una respuesta que llega tarde, de una
// sesión anterior, se descarta. `busqueda` hace lo mismo con búsquedas viejas.
let sesion = 0;
let busqueda = 0;
let temporizador = null;

// ------------------------------------------------------------------------------------------------
// Render: estado → DOM. Se conserva el foco y el cursor del campo activo al redibujar.

function vistaActual(estado) {
    switch (estado.vista) {
        case 'login': return crearVistaLogin(estado);
        case 'verificando': return crearVistaVerificando(estado);
        case 'disponibles': return crearLayout(estado, crearVistaDisponibles(estado));
        default: return crearLayout(estado, crearVistaCartera(estado));
    }
}

function render(estado) {
    const activo = document.activeElement;
    const idActivo = activo && activo !== document.body ? activo.id : '';
    let seleccion = null;
    try {
        seleccion = activo && typeof activo.selectionStart === 'number' ? [activo.selectionStart, activo.selectionEnd] : null;
    } catch {
        seleccion = null;
    }

    app.replaceChildren(vistaActual(estado));

    if (idActivo) {
        const nuevo = document.getElementById(idActivo);
        if (nuevo && typeof nuevo.focus === 'function') {
            nuevo.focus({ preventScroll: true });
            if (seleccion) {
                try {
                    nuevo.setSelectionRange(...seleccion);
                } catch {
                    // type=email no admite selección.
                }
            }
        }
    }
}

// ------------------------------------------------------------------------------------------------
// Sesión.

configurarApi({
    pendientes: (cambio) => actualizar((e) => { e.pendientes = Math.max(0, e.pendientes + cambio); }),
    noAutorizado: () => terminarSesion(MENSAJE_SESION_VENCIDA),
});

function terminarSesion(aviso = null) {
    sesion += 1;
    busqueda += 1;
    clearTimeout(temporizador);
    borrarToken();
    actualizar((e) => {
        const { pendientes, login } = e;
        Object.assign(e, estadoInicial(null));
        e.pendientes = pendientes;
        e.login.correo = login.correo;
        e.login.aviso = aviso;
    });
    if (window.location.hash) {
        history.replaceState(null, '', window.location.pathname + window.location.search);
    }
}

function vistaDesdeHash() {
    return window.location.hash === '#/disponibles' ? 'disponibles' : 'cartera';
}

/** Un error de una petición con sesión: el 401 ya se atendió (volvió al login). */
function describirError(error) {
    if (error instanceof ApiError) {
        return error.status === 401 ? null : (error.payload?.error ?? MENSAJE_GENERICO);
    }
    if (error instanceof ConexionError) {
        return error.message;
    }
    console.error(error);
    return MENSAJE_GENERICO;
}

/** Al abrir la SPA con un token guardado se valida contra una ruta protegida (GET /clientes). */
async function verificarSesion() {
    const miSesion = sesion;
    actualizar((e) => { e.vista = 'verificando'; e.conexion = null; });
    const correcto = await cargarCartera({ conTotal: true });
    if (correcto && miSesion === sesion) {
        irA(vistaDesdeHash());
    }
}

async function procesarLogin(form) {
    const datos = new FormData(form);
    const correo = String(datos.get('correo') ?? '').trim();
    const clave = String(datos.get('clave') ?? '');

    actualizar((e) => {
        e.login = { correo, errores: {}, error: null, aviso: null, enviando: true };
        e.conexion = null;
    });

    try {
        const { token } = await iniciarSesion(correo, clave);
        guardarToken(token);
        sesion += 1;
        actualizar((e) => {
            Object.assign(e, estadoInicial(token));
            e.pendientes = obtenerEstado().pendientes;
        });
        await verificarSesion();
    } catch (error) {
        actualizar((e) => {
            e.login.enviando = false;
            if (error instanceof ApiError && error.status === 422) {
                e.login.errores = error.payload?.errores ?? {};
            } else if (error instanceof ApiError && [401, 403].includes(error.status)) {
                e.login.error = error.payload?.error ?? 'No se pudo iniciar sesión.'; // F1.2: mensaje del 403
            } else {
                e.login.error = describirError(error);
            }
        });
    }
}

// ------------------------------------------------------------------------------------------------
// Datos.

/**
 * Carga la cartera con la búsqueda actual (en el servidor). El contador «x de 10» es la cartera
 * completa: si hay búsqueda, se pide también la lista sin filtro cuando hace falta.
 * Devuelve true si todo salió bien.
 */
async function cargarCartera({ conTotal = false } = {}) {
    const miSesion = sesion;
    const miBusqueda = ++busqueda;
    const { token, cartera } = obtenerEstado();
    const q = cartera.q.trim();
    actualizar((e) => { e.cartera.cargando = true; e.cartera.error = null; });

    try {
        const filtrada = listarCartera(token, q);
        const completa = q !== '' && (conTotal || cartera.total === null) ? listarCartera(token, '') : null;
        const [clientes, todos] = await Promise.all([filtrada, completa]);
        if (miSesion !== sesion || miBusqueda !== busqueda) {
            return false;
        }
        actualizar((e) => {
            e.cartera.clientes = clientes;
            e.cartera.cargada = true;
            e.cartera.cargando = false;
            e.conexion = null;
            if (q === '') {
                e.cartera.total = clientes.length;
            } else if (todos) {
                e.cartera.total = todos.length;
            }
        });
        return true;
    } catch (error) {
        if (miSesion === sesion && miBusqueda === busqueda) {
            const mensaje = describirError(error);
            actualizar((e) => {
                e.cartera.cargando = false;
                if (error instanceof ConexionError) {
                    e.conexion = mensaje;
                } else if (mensaje) {
                    e.cartera.error = mensaje;
                }
            });
        }
        return false;
    }
}

async function cargarDisponibles() {
    const miSesion = sesion;
    actualizar((e) => { e.disponibles.cargando = true; });

    try {
        const lista = await listarDisponibles(obtenerEstado().token);
        if (miSesion !== sesion) {
            return;
        }
        actualizar((e) => {
            e.disponibles.lista = lista.map(({ id, nombre }) => ({ id, nombre }));
            e.disponibles.cargada = true;
            e.disponibles.cargando = false;
            e.conexion = null;
        });
    } catch (error) {
        if (miSesion === sesion) {
            const mensaje = describirError(error);
            actualizar((e) => {
                e.disponibles.cargando = false;
                if (error instanceof ConexionError) {
                    e.conexion = mensaje;
                } else if (mensaje) {
                    e.disponibles.mensaje = mensaje;
                }
            });
        }
    }
}

function irA(vista) {
    actualizar((e) => { e.vista = vista; });
    if (vista === 'disponibles') {
        cargarDisponibles();
    } else {
        cargarCartera({ conTotal: true });
    }
}

async function enviarAlta(form) {
    const datos = new FormData(form);
    const valores = Object.fromEntries(['nombre', 'nit', 'telefono', 'correo']
        .map((campo) => [campo, String(datos.get(campo) ?? '')]));
    const miSesion = sesion;

    actualizar((e) => {
        e.alta.datos = valores;
        e.alta.errores = {};
        e.alta.error = null;
        e.alta.enviando = true;
        e.aviso = null;
    });

    // Se envía lo escrito; el servidor normaliza y valida (no se repiten sus reglas aquí).
    const cuerpo = { nombre: valores.nombre, nit: valores.nit, telefono: valores.telefono };
    if (valores.correo.trim() !== '') {
        cuerpo.correo = valores.correo;
    }

    try {
        const cliente = await crearCliente(obtenerEstado().token, cuerpo);
        if (miSesion !== sesion) {
            return;
        }
        actualizar((e) => {
            e.alta = altaVacia();
            e.aviso = `Se agregó «${cliente.nombre}» a su cartera.`;
        });
        await cargarCartera({ conTotal: true });
    } catch (error) {
        if (miSesion !== sesion) {
            return;
        }
        actualizar((e) => {
            e.alta.enviando = false;
            if (error instanceof ApiError && error.status === 422) {
                e.alta.errores = error.payload?.errores ?? {};
            } else {
                // 409: NIT o teléfono repetido, o cartera llena; el mensaje es el del servidor.
                e.alta.error = describirError(error);
            }
        });
    }
}

async function tomar(id) {
    const miSesion = sesion;
    actualizar((e) => {
        e.disponibles.tomando = id;
        e.disponibles.mensaje = null;
        e.aviso = null;
    });

    try {
        const cliente = await tomarCliente(obtenerEstado().token, id);
        if (miSesion !== sesion) {
            return;
        }
        // F2.5: sale de disponibles y el contador sube sin recargar la página.
        actualizar((e) => {
            e.disponibles.tomando = null;
            e.disponibles.lista = e.disponibles.lista.filter((disponible) => disponible.id !== id);
            e.cartera.total = (e.cartera.total ?? 0) + 1;
            e.aviso = `«${cliente.nombre}» ya está en su cartera.`;
        });
        cargarCartera({ conTotal: true });
    } catch (error) {
        if (miSesion !== sesion) {
            return;
        }
        const mensaje = describirError(error);
        actualizar((e) => {
            e.disponibles.tomando = null;
            if (error instanceof ConexionError) {
                e.conexion = mensaje;
            } else if (mensaje) {
                e.disponibles.mensaje = mensaje; // F2.3: el mensaje del 409, tal como lo manda el servidor
            }
        });
        if (error instanceof ApiError && error.status !== 401) {
            cargarDisponibles();
            cargarCartera({ conTotal: true });
        }
    }
}

// ------------------------------------------------------------------------------------------------
// Delegación de eventos: un solo oyente por tipo, en #app.

app.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-form]');
    if (!form) {
        return;
    }
    event.preventDefault();
    if (form.dataset.form === 'login') {
        procesarLogin(form);
    } else if (form.dataset.form === 'alta') {
        enviarAlta(form);
    }
});

app.addEventListener('input', (event) => {
    const campo = event.target;
    const form = campo.closest('[data-form]')?.dataset.form;

    if (form === 'login' && campo.name === 'correo') {
        actualizar((e) => { e.login.correo = campo.value; }, { render: false });
    } else if (form === 'alta' && campo.name in obtenerEstado().alta.datos) {
        actualizar((e) => { e.alta.datos[campo.name] = campo.value; }, { render: false });
    } else if (campo.id === 'busqueda') {
        actualizar((e) => { e.cartera.q = campo.value; }, { render: false });
        clearTimeout(temporizador);
        temporizador = setTimeout(() => cargarCartera(), ESPERA_BUSQUEDA_MS);
    }
});

app.addEventListener('click', (event) => {
    const boton = event.target.closest('[data-action]');
    if (!boton) {
        return;
    }

    switch (boton.dataset.action) {
        case 'logout':
            terminarSesion(null);
            break;
        case 'abrir-alta':
            actualizar((e) => { e.alta = { ...altaVacia(), abierta: true }; e.aviso = null; });
            document.getElementById('alta-nombre')?.focus();
            break;
        case 'cancelar-alta':
            actualizar((e) => { e.alta = altaVacia(); });
            break;
        case 'tomar':
            tomar(Number(boton.dataset.id));
            break;
        case 'cerrar-aviso':
            actualizar((e) => { e.aviso = null; });
            break;
        case 'reintentar':
            actualizar((e) => { e.conexion = null; });
            if (obtenerEstado().vista === 'verificando') {
                verificarSesion();
            } else {
                irA(obtenerEstado().vista);
            }
            break;
        default:
            break;
    }
});

window.addEventListener('hashchange', () => {
    const { token, vista } = obtenerEstado();
    if (token && vista !== 'verificando' && vista !== vistaDesdeHash()) {
        irA(vistaDesdeHash());
    }
});

suscribirse(render);
if (obtenerEstado().token) {
    verificarSesion();
}
