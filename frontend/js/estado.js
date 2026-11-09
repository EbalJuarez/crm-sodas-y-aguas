/**
 * Estado único de la SPA (estado → render). Las vistas solo leen; los cambios pasan por
 * actualizar(), que vuelve a dibujar salvo que se pida { render: false } (p. ej. al escribir en
 * un campo: el valor se guarda sin redibujar para no perder el cursor).
 *
 * localStorage guarda SOLO el token de la sesión (F1.5).
 */
const TOKEN_KEY = 'crm_agua_token';

export function leerToken() {
    try {
        return localStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

export function guardarToken(token) {
    try {
        localStorage.setItem(TOKEN_KEY, token);
    } catch {
        // Sin almacenamiento la sesión dura lo que dure la pestaña.
    }
}

export function borrarToken() {
    try {
        localStorage.removeItem(TOKEN_KEY);
    } catch {
        // Nada que borrar.
    }
}

export function altaVacia() {
    return {
        abierta: false,
        datos: { nombre: '', nit: '', telefono: '', correo: '' },
        errores: {},
        error: null,
        enviando: false,
    };
}

export function nuevaOportunidadVacia() {
    return {
        abierta: false,
        datos: { cliente_id: '', titulo: '' },
        errores: {},
        error: null,
        enviando: false,
    };
}

export function detalleVacio() {
    return {
        id: null,
        datos: null,
        catalogo: [],
        cargando: false,
        error: null,
        titulo: '',
        tituloErrores: {},
        guardandoTitulo: false,
        producto: { producto_id: '', cantidad: '', errores: {}, error: null, enviando: false },
        etapa: { etapa: '', motivo: '', errores: {}, error: null, enviando: false },
    };
}

export function nuevaActividadVacia() {
    return {
        abierta: false,
        datos: { cliente_id: '', tipo: 'llamada', descripcion: '', vence_en: '' },
        errores: {},
        error: null,
        enviando: false,
    };
}

/** vista: 'login' | 'verificando' | 'cartera' | 'disponibles' | 'embudo' | 'detalle'. */
export function estadoInicial(token) {
    return {
        token,
        vista: token ? 'verificando' : 'login',
        pendientes: 0,
        conexion: null,
        aviso: null,
        login: { correo: '', errores: {}, error: null, aviso: null, enviando: false },
        cartera: { clientes: [], total: null, q: '', cargando: false, cargada: false, error: null },
        alta: altaVacia(),
        disponibles: { lista: [], cargando: false, cargada: false, mensaje: null, tomando: null },
        oportunidades: {
            lista: [], cargando: false, cargada: false, error: null, nueva: nuevaOportunidadVacia(),
        },
        detalle: detalleVacio(),
        actividades: {
            lista: [], filtro: '', cargando: false, cargada: false, error: null,
            nueva: nuevaActividadVacia(), actualizando: null,
        },
        reporte: { mes: '', datos: null, cargando: false, cargado: false, error: null },
    };
}

let estado = estadoInicial(leerToken());
const suscriptores = new Set();

export function obtenerEstado() {
    return estado;
}

export function suscribirse(render) {
    suscriptores.add(render);
    render(estado);
    return () => suscriptores.delete(render);
}

/** receta(borrador) modifica una copia; luego se reemplaza el estado y se redibuja. */
export function actualizar(receta, { render = true } = {}) {
    const borrador = structuredClone(estado);
    receta(borrador);
    estado = borrador;
    if (render) {
        suscriptores.forEach((fn) => fn(estado));
    }
}
