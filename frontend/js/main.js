import {
    ApiError,
    ConexionError,
    configurarApi,
    crearCliente,
    iniciarSesion,
    listarCartera,
    listarDisponibles,
    listarOportunidades,
    crearOportunidad,
    obtenerOportunidad,
    editarOportunidad,
    cambiarEtapaOportunidad,
    listarProductos,
    agregarProductoOportunidad,
    cambiarCantidadProducto,
    eliminarProductoOportunidad,
    listarActividades,
    crearActividad,
    cambiarActividad,
    obtenerResumen,
    tomarCliente,
} from './api.js';
import { ESPERA_BUSQUEDA_MS } from './config.js';
import {
    actualizar,
    altaVacia,
    detalleVacio,
    nuevaOportunidadVacia,
    nuevaActividadVacia,
    borrarToken,
    estadoInicial,
    guardarToken,
    obtenerEstado,
    suscribirse,
} from './estado.js';
import { crearVistaCartera } from './vistas/cartera.js';
import { crearVistaDisponibles } from './vistas/disponibles.js';
import { crearVistaEmbudo } from './vistas/embudo.js';
import { crearVistaDetalle } from './vistas/detalle-oportunidad.js';
import { crearVistaActividades } from './vistas/actividades.js';
import { crearVistaResumen } from './vistas/resumen.js';
import { crearLayout, crearVistaVerificando } from './vistas/layout.js';
import { crearVistaLogin } from './vistas/login.js';
import { fechaLocalGuatemalaAISO, mesActualGuatemala } from './util/fechas.js';

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
        case 'embudo': return crearLayout(estado, crearVistaEmbudo(estado));
        case 'detalle': return crearLayout(estado, crearVistaDetalle(estado));
        case 'actividades': return crearLayout(estado, crearVistaActividades(estado));
        case 'resumen': return crearLayout(estado, crearVistaResumen(estado));
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

function rutaDesdeHash() {
    const coincidencia = window.location.hash.match(/^#\/oportunidades\/(\d+)$/);
    if (coincidencia) {
        return { vista: 'detalle', id: Number(coincidencia[1]) };
    }
    if (window.location.hash === '#/disponibles') {
        return { vista: 'disponibles', id: null };
    }
    if (window.location.hash === '#/embudo') {
        return { vista: 'embudo', id: null };
    }
    if (window.location.hash === '#/actividades') {
        return { vista: 'actividades', id: null };
    }
    if (window.location.hash === '#/resumen') {
        return { vista: 'resumen', id: null };
    }
    return { vista: 'cartera', id: null };
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
        const ruta = rutaDesdeHash();
        irA(ruta.vista, ruta.id);
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

async function cargarOportunidades() {
    const miSesion = sesion;
    actualizar((e) => { e.oportunidades.cargando = true; e.oportunidades.error = null; });
    try {
        const lista = await listarOportunidades(obtenerEstado().token);
        if (miSesion !== sesion) return;
        actualizar((e) => {
            e.oportunidades.lista = lista;
            e.oportunidades.cargada = true;
            e.oportunidades.cargando = false;
            e.conexion = null;
        });
    } catch (error) {
        if (miSesion !== sesion) return;
        const mensaje = describirError(error);
        actualizar((e) => {
            e.oportunidades.cargando = false;
            if (error instanceof ConexionError) e.conexion = mensaje;
            else if (mensaje) e.oportunidades.error = mensaje;
        });
    }
}

async function cargarActividades() {
    const miSesion = sesion;
    const filtro = obtenerEstado().actividades.filtro;
    actualizar((e) => { e.actividades.cargando = true; e.actividades.error = null; });
    try {
        const lista = await listarActividades(obtenerEstado().token, filtro);
        if (miSesion !== sesion) return;
        actualizar((e) => {
            e.actividades.lista = lista;
            e.actividades.cargada = true;
            e.actividades.cargando = false;
            e.actividades.actualizando = null;
            e.conexion = null;
        });
    } catch (error) {
        if (miSesion !== sesion) return;
        const mensaje = describirError(error);
        actualizar((e) => {
            e.actividades.cargando = false;
            e.actividades.actualizando = null;
            if (error instanceof ConexionError) e.conexion = mensaje;
            else if (mensaje) e.actividades.error = mensaje;
        });
    }
}

async function cargarResumen(mes = obtenerEstado().reporte.mes || mesActualGuatemala()) {
    const miSesion = sesion;
    actualizar((e) => { e.reporte.mes = mes; e.reporte.cargando = true; e.reporte.error = null; });
    try {
        const datos = await obtenerResumen(obtenerEstado().token, mes);
        if (miSesion !== sesion) return;
        actualizar((e) => {
            e.reporte.datos = datos;
            e.reporte.mes = datos.mes;
            e.reporte.cargado = true;
            e.reporte.cargando = false;
            e.conexion = null;
        });
    } catch (error) {
        if (miSesion !== sesion) return;
        const mensaje = describirError(error);
        actualizar((e) => {
            e.reporte.cargando = false;
            if (error instanceof ConexionError) e.conexion = mensaje;
            else if (mensaje) e.reporte.error = mensaje;
        });
    }
}

async function cargarDetalle(id) {
    const miSesion = sesion;
    actualizar((e) => {
        e.detalle = { ...detalleVacio(), id, cargando: true };
    });
    try {
        const [datos, catalogo] = await Promise.all([
            obtenerOportunidad(obtenerEstado().token, id),
            listarProductos(obtenerEstado().token),
        ]);
        if (miSesion !== sesion) return;
        actualizar((e) => {
            e.detalle = {
                ...detalleVacio(), id, datos, catalogo, titulo: datos.titulo,
                cargando: false,
            };
            e.conexion = null;
        });
    } catch (error) {
        if (miSesion !== sesion) return;
        const mensaje = describirError(error);
        actualizar((e) => {
            e.detalle.cargando = false;
            e.detalle.error = mensaje;
            if (error instanceof ConexionError) e.conexion = mensaje;
        });
    }
}

function irA(vista, id = null) {
    actualizar((e) => { e.vista = vista; });
    if (vista === 'disponibles') {
        cargarDisponibles();
    } else if (vista === 'embudo') {
        cargarOportunidades();
    } else if (vista === 'detalle') {
        cargarDetalle(id);
    } else if (vista === 'actividades') {
        cargarActividades();
    } else if (vista === 'resumen') {
        cargarResumen();
    } else {
        cargarCartera({ conTotal: true });
    }
}

async function enviarNuevaActividad(form) {
    const valores = new FormData(form);
    const venceEnLocal = String(valores.get('vence_en') ?? '');
    const datos = {
        tipo: String(valores.get('tipo') ?? ''),
        descripcion: String(valores.get('descripcion') ?? ''),
    };
    if (venceEnLocal !== '') {
        datos.vence_en = fechaLocalGuatemalaAISO(venceEnLocal) ?? venceEnLocal;
    }
    actualizar((e) => {
        e.actividades.nueva.datos = {
            cliente_id: String(valores.get('cliente_id') ?? ''), tipo: datos.tipo,
            descripcion: datos.descripcion, vence_en: venceEnLocal,
        };
        e.actividades.nueva.errores = {};
        e.actividades.nueva.error = null;
        e.actividades.nueva.enviando = true;
    });
    try {
        await crearActividad(obtenerEstado().token, Number(valores.get('cliente_id')), datos);
        actualizar((e) => { e.actividades.nueva = nuevaActividadVacia(); });
        cargarActividades();
    } catch (error) {
        actualizar((e) => {
            e.actividades.nueva.enviando = false;
            if (error instanceof ApiError && error.status === 422) e.actividades.nueva.errores = erroresDe(error);
            else e.actividades.nueva.error = describirError(error);
        });
    }
}

async function actualizarActividad(id, completada) {
    actualizar((e) => { e.actividades.actualizando = id; e.actividades.error = null; });
    try {
        await cambiarActividad(obtenerEstado().token, id, completada);
        cargarActividades();
    } catch (error) {
        actualizar((e) => {
            e.actividades.actualizando = null;
            e.actividades.error = describirError(error);
        });
    }
}

function abrirRuta(vista, id = null) {
    const hash = vista === 'detalle' ? `#/oportunidades/${id}` : `#/${vista}`;
    if (window.location.hash === hash) {
        irA(vista, id);
    } else {
        window.location.hash = hash;
    }
}

function erroresDe(error) {
    return error instanceof ApiError && error.status === 422 ? (error.payload?.errores ?? {}) : {};
}

function actualizarDetalle(datos) {
    actualizar((e) => {
        e.detalle.datos = datos;
        e.detalle.titulo = datos.titulo;
        e.detalle.producto = { producto_id: '', cantidad: '', errores: {}, error: null, enviando: false };
        e.detalle.etapa = { etapa: '', motivo: '', errores: {}, error: null, enviando: false };
        e.conexion = null;
    });
}

async function enviarNuevaOportunidad(form) {
    const datosFormulario = new FormData(form);
    const datos = {
        cliente_id: Number(datosFormulario.get('cliente_id')),
        titulo: String(datosFormulario.get('titulo') ?? ''),
    };
    actualizar((e) => {
        e.oportunidades.nueva.datos = { cliente_id: String(datos.cliente_id || ''), titulo: datos.titulo };
        e.oportunidades.nueva.errores = {};
        e.oportunidades.nueva.error = null;
        e.oportunidades.nueva.enviando = true;
    });
    try {
        const oportunidad = await crearOportunidad(obtenerEstado().token, datos);
        actualizar((e) => { e.oportunidades.nueva = nuevaOportunidadVacia(); });
        abrirRuta('detalle', oportunidad.id);
    } catch (error) {
        actualizar((e) => {
            e.oportunidades.nueva.enviando = false;
            if (error instanceof ApiError && error.status === 422) e.oportunidades.nueva.errores = erroresDe(error);
            else e.oportunidades.nueva.error = describirError(error);
        });
    }
}

async function enviarTitulo(form) {
    const titulo = String(new FormData(form).get('titulo') ?? '');
    const { id } = obtenerEstado().detalle;
    actualizar((e) => { e.detalle.titulo = titulo; e.detalle.tituloErrores = {}; e.detalle.guardandoTitulo = true; });
    try {
        actualizarDetalle(await editarOportunidad(obtenerEstado().token, id, { titulo }));
    } catch (error) {
        actualizar((e) => {
            e.detalle.guardandoTitulo = false;
            e.detalle.tituloErrores = error instanceof ApiError && error.status === 422 ? erroresDe(error) : { _: describirError(error) };
        });
    }
}

async function enviarProducto(form) {
    const valores = new FormData(form);
    const { id } = obtenerEstado().detalle;
    const datos = { producto_id: Number(valores.get('producto_id')), cantidad: Number(valores.get('cantidad')) };
    actualizar((e) => { e.detalle.producto.errores = {}; e.detalle.producto.error = null; e.detalle.producto.enviando = true; });
    try {
        actualizarDetalle(await agregarProductoOportunidad(obtenerEstado().token, id, datos));
    } catch (error) {
        actualizar((e) => {
            e.detalle.producto.enviando = false;
            if (error instanceof ApiError && error.status === 422) e.detalle.producto.errores = erroresDe(error);
            else e.detalle.producto.error = describirError(error);
        });
    }
}

async function cambiarCantidad(idLinea) {
    const { id } = obtenerEstado().detalle;
    const cantidad = Number(document.getElementById(`cantidad-${idLinea}`)?.value);
    try {
        actualizarDetalle(await cambiarCantidadProducto(obtenerEstado().token, id, idLinea, cantidad));
    } catch (error) {
        actualizar((e) => {
            if (error instanceof ApiError && error.status === 422) e.detalle.producto.errores = erroresDe(error);
            else e.detalle.producto.error = describirError(error);
        });
    }
}

async function eliminarProducto(idLinea) {
    const { id } = obtenerEstado().detalle;
    try {
        await eliminarProductoOportunidad(obtenerEstado().token, id, idLinea);
        await cargarDetalle(id);
    } catch (error) {
        actualizar((e) => { e.detalle.producto.error = describirError(error); });
    }
}

async function enviarEtapa(form) {
    const valores = new FormData(form);
    const etapa = String(valores.get('etapa') ?? '');
    const datos = { etapa };
    if (etapa === 'perdida') datos.motivo_perdida = String(valores.get('motivo_perdida') ?? '');
    const { id } = obtenerEstado().detalle;
    actualizar((e) => {
        e.detalle.etapa.etapa = etapa;
        e.detalle.etapa.motivo = String(valores.get('motivo_perdida') ?? '');
        e.detalle.etapa.errores = {};
        e.detalle.etapa.error = null;
        e.detalle.etapa.enviando = true;
    });
    try {
        actualizarDetalle(await cambiarEtapaOportunidad(obtenerEstado().token, id, datos));
        cargarOportunidades();
    } catch (error) {
        actualizar((e) => {
            e.detalle.etapa.enviando = false;
            if (error instanceof ApiError && error.status === 422) e.detalle.etapa.errores = erroresDe(error);
            else e.detalle.etapa.error = describirError(error);
        });
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
    } else if (form.dataset.form === 'crear-oportunidad') {
        enviarNuevaOportunidad(form);
    } else if (form.dataset.form === 'editar-titulo') {
        enviarTitulo(form);
    } else if (form.dataset.form === 'agregar-producto') {
        enviarProducto(form);
    } else if (form.dataset.form === 'cambiar-etapa') {
        enviarEtapa(form);
    } else if (form.dataset.form === 'crear-actividad') {
        enviarNuevaActividad(form);
    }
});

app.addEventListener('input', (event) => {
    const campo = event.target;
    const form = campo.closest('[data-form]')?.dataset.form;

    if (form === 'login' && campo.name === 'correo') {
        actualizar((e) => { e.login.correo = campo.value; }, { render: false });
    } else if (form === 'alta' && campo.name in obtenerEstado().alta.datos) {
        actualizar((e) => { e.alta.datos[campo.name] = campo.value; }, { render: false });
    } else if (form === 'crear-oportunidad' && campo.name in obtenerEstado().oportunidades.nueva.datos) {
        actualizar((e) => { e.oportunidades.nueva.datos[campo.name] = campo.value; }, { render: false });
    } else if (form === 'editar-titulo' && campo.name === 'titulo') {
        actualizar((e) => { e.detalle.titulo = campo.value; }, { render: false });
    } else if (form === 'agregar-producto' && ['producto_id', 'cantidad'].includes(campo.name)) {
        actualizar((e) => { e.detalle.producto[campo.name] = campo.value; }, { render: false });
    } else if (form === 'cambiar-etapa' && ['etapa', 'motivo_perdida'].includes(campo.name)) {
        actualizar((e) => {
            if (campo.name === 'etapa') e.detalle.etapa.etapa = campo.value;
            else e.detalle.etapa.motivo = campo.value;
        }, { render: false });
    } else if (form === 'crear-actividad' && ['cliente_id', 'tipo', 'descripcion', 'vence_en'].includes(campo.name)) {
        actualizar((e) => { e.actividades.nueva.datos[campo.name] = campo.value; }, { render: false });
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
        case 'abrir-nueva-oportunidad':
            actualizar((e) => { e.oportunidades.nueva = { ...nuevaOportunidadVacia(), abierta: true }; });
            break;
        case 'cancelar-nueva-oportunidad':
            actualizar((e) => { e.oportunidades.nueva = nuevaOportunidadVacia(); });
            break;
        case 'abrir-oportunidad':
            abrirRuta('detalle', Number(boton.dataset.id));
            break;
        case 'volver-embudo':
            abrirRuta('embudo');
            break;
        case 'cambiar-cantidad':
            cambiarCantidad(Number(boton.dataset.id));
            break;
        case 'eliminar-producto':
            eliminarProducto(Number(boton.dataset.id));
            break;
        case 'abrir-nueva-actividad':
            actualizar((e) => { e.actividades.nueva = { ...nuevaActividadVacia(), abierta: true }; });
            break;
        case 'cancelar-nueva-actividad':
            actualizar((e) => { e.actividades.nueva = nuevaActividadVacia(); });
            break;
        case 'cambiar-actividad':
            actualizarActividad(Number(boton.dataset.id), boton.dataset.completada === 'true');
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
    const ruta = rutaDesdeHash();
    if (token && vista !== 'verificando') {
        irA(ruta.vista, ruta.id);
    }
});

app.addEventListener('change', (event) => {
    const campo = event.target;
    if (campo.id === 'filtro-actividades') {
        actualizar((e) => { e.actividades.filtro = campo.value; e.actividades.cargada = false; });
        cargarActividades();
        return;
    }
    if (campo.id === 'resumen-mes' || campo.id === 'resumen-anio') {
        const mes = campo.id === 'resumen-mes' ? campo.value : document.getElementById('resumen-mes')?.value;
        const anio = campo.id === 'resumen-anio' ? campo.value : document.getElementById('resumen-anio')?.value;
        if (mes && anio) cargarResumen(`${anio}-${mes}`);
        return;
    }
    if (campo.closest('[data-form="cambiar-etapa"]') && ['etapa', 'motivo_perdida'].includes(campo.name)) {
        actualizar((e) => {
            if (campo.name === 'etapa') e.detalle.etapa.etapa = campo.value;
            else e.detalle.etapa.motivo = campo.value;
        });
    }
});

suscribirse(render);
if (obtenerEstado().token) {
    verificarSesion();
}
