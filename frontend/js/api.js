import { sesionNoAutorizada } from './estado.js';

const API_BASE_URL = 'http://127.0.0.1:3000/api';

export class ApiError extends Error {
    constructor(status, payload) {
        super(payload?.error ?? 'La API respondió con un error.');
        this.name = 'ApiError';
        this.status = status;
        this.payload = payload;
    }
}

export async function iniciarSesion(correo, clave) {
    return solicitar('/auth/login', {
        method: 'POST',
        body: { correo, clave },
    });
}

export async function solicitar(path, { method = 'GET', body, token } = {}) {
    const headers = { Accept: 'application/json' };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    let response;
    try {
        response = await fetch(`${API_BASE_URL}${path}`, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        const error = new Error('No se pudo conectar con la API. Compruebe que esté en marcha.');
        error.name = 'ConexionError';
        throw error;
    }

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok) {
        if (response.status === 401) {
            sesionNoAutorizada();
        }
        throw new ApiError(response.status, payload);
    }

    return payload;
}
