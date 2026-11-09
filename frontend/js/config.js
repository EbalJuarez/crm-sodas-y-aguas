// Deben coincidir EXACTAMENTE con api/.env: PORT=3000 y CORS_ORIGIN=http://127.0.0.1:5500.
// Abra la SPA con Live Server desde http://127.0.0.1:5500 (no desde localhost:5500): con otro
// origen el navegador bloquea las peticiones por CORS (S6).
export const API_BASE_URL = 'http://127.0.0.1:3000/api';
export const ORIGEN_SPA = 'http://127.0.0.1:5500';

// R3: cartera máxima de un vendedor (contrato: ResumenMes.cartera.maximo).
export const MAXIMO_CARTERA = 10;

// Espera entre la última tecla y la búsqueda en el servidor.
export const ESPERA_BUSQUEDA_MS = 300;
