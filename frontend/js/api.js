import { API_BASE_URL } from './config.js';

/**
 * Único módulo que habla con la API. No conoce el estado: main.js le pasa dos avisos
 * (configurarApi) para el indicador de carga y para un 401 en plena sesión.
 */
let avisos = {
    pendientes: () => {},
    noAutorizado: () => {},
};

export function configurarApi(nuevos) {
    avisos = { ...avisos, ...nuevos };
}

export class ApiError extends Error {
    constructor(status, payload) {
        super(payload?.error ?? 'La API respondió con un error.');
        this.name = 'ApiError';
        this.status = status;
        this.payload = payload;
    }
}

export class ConexionError extends Error {
    constructor() {
        super('No se pudo conectar con la API. Compruebe que esté en marcha (npm start en api/) e inténtelo de nuevo.');
        this.name = 'ConexionError';
    }
}

export async function solicitar(ruta, { method = 'GET', body, token } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    avisos.pendientes(1);
    try {
        let respuesta;
        try {
            respuesta = await fetch(`${API_BASE_URL}${ruta}`, {
                method,
                headers,
                body: body === undefined ? undefined : JSON.stringify(body),
            });
        } catch {
            throw new ConexionError();
        }

        let payload = null;
        if (respuesta.status !== 204) {
            try {
                payload = await respuesta.json();
            } catch {
                payload = null;
            }
        }

        if (!respuesta.ok) {
            // Solo una petición CON token puede significar «sesión vencida» (token alterado,
            // vencido o cuenta desactivada). El 401 del login es «credenciales incorrectas».
            if (respuesta.status === 401 && token) {
                avisos.noAutorizado();
            }
            throw new ApiError(respuesta.status, payload);
        }

        return payload;
    } finally {
        avisos.pendientes(-1);
    }
}

export function iniciarSesion(correo, clave) {
    return solicitar('/auth/login', { method: 'POST', body: { correo, clave } });
}

export function listarCartera(token, q = '') {
    const consulta = q.trim() === '' ? '' : `?q=${encodeURIComponent(q.trim())}`;
    return solicitar(`/clientes${consulta}`, { token });
}

export function listarDisponibles(token) {
    return solicitar('/clientes/disponibles', { token });
}

export function crearCliente(token, datos) {
    return solicitar('/clientes', { method: 'POST', body: datos, token });
}

export function tomarCliente(token, id) {
    return solicitar(`/clientes/${encodeURIComponent(id)}/tomar`, { method: 'POST', token });
}

export function listarOportunidades(token, etapa = '') {
    const consulta = etapa === '' ? '' : `?etapa=${encodeURIComponent(etapa)}`;
    return solicitar(`/oportunidades${consulta}`, { token });
}

export function crearOportunidad(token, datos) {
    return solicitar('/oportunidades', { method: 'POST', body: datos, token });
}

export function obtenerOportunidad(token, id) {
    return solicitar(`/oportunidades/${encodeURIComponent(id)}`, { token });
}

export function editarOportunidad(token, id, datos) {
    return solicitar(`/oportunidades/${encodeURIComponent(id)}`, { method: 'PATCH', body: datos, token });
}

export function cambiarEtapaOportunidad(token, id, datos) {
    return solicitar(`/oportunidades/${encodeURIComponent(id)}/etapa`, { method: 'PATCH', body: datos, token });
}

export function listarProductos(token) {
    return solicitar('/productos', { token });
}

export function agregarProductoOportunidad(token, oportunidadId, datos) {
    return solicitar(`/oportunidades/${encodeURIComponent(oportunidadId)}/productos`, {
        method: 'POST', body: datos, token,
    });
}

export function cambiarCantidadProducto(token, oportunidadId, lineaId, cantidad) {
    return solicitar(`/oportunidades/${encodeURIComponent(oportunidadId)}/productos/${encodeURIComponent(lineaId)}`, {
        method: 'PATCH', body: { cantidad }, token,
    });
}

export function eliminarProductoOportunidad(token, oportunidadId, lineaId) {
    return solicitar(`/oportunidades/${encodeURIComponent(oportunidadId)}/productos/${encodeURIComponent(lineaId)}`, {
        method: 'DELETE', token,
    });
}

export function listarActividades(token, estado = '') {
    const consulta = estado === '' ? '' : `?estado=${encodeURIComponent(estado)}`;
    return solicitar(`/actividades${consulta}`, { token });
}

export function crearActividad(token, clienteId, datos) {
    return solicitar(`/clientes/${encodeURIComponent(clienteId)}/actividades`, {
        method: 'POST', body: datos, token,
    });
}

export function cambiarActividad(token, id, completada) {
    return solicitar(`/actividades/${encodeURIComponent(id)}`, {
        method: 'PATCH', body: { completada }, token,
    });
}

export function obtenerResumen(token, mes = '') {
    const consulta = mes === '' ? '' : `?mes=${encodeURIComponent(mes)}`;
    return solicitar(`/reportes/resumen${consulta}`, { token });
}
