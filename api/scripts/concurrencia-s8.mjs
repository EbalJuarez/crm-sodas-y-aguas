import mysql from 'mysql2/promise';

// El lanzador prepara crm_agua_test con VerificacionSeeder antes de cada ronda.
// Este script solo ejerce las dos puertas HTTP y comprueba D2/D3 al terminar.
const API = process.env.API_ORIGIN ?? 'http://127.0.0.1:3000';
const PANEL = process.env.PANEL_ORIGIN ?? 'http://127.0.0.1:8001';
const CLAVE = process.env.CLAVE_SEMILLA;
const BASE = process.env.DB_DATABASE;
const CLIENTE_ID = 3;

if (BASE !== 'crm_agua_test') {
  throw new Error('La prueba S8 solo puede ejecutarse con DB_DATABASE=crm_agua_test.');
}
if (!CLAVE) {
  throw new Error('Defina CLAVE_SEMILLA en el entorno; no se guarda ninguna credencial en el script.');
}

function cookiesDe(respuesta, cookies = new Map()) {
  for (const valor of respuesta.headers.getSetCookie()) {
    const [par] = valor.split(';', 1);
    const [nombre, contenido] = par.split('=', 2);
    cookies.set(nombre, contenido);
  }
  return cookies;
}

function encabezadoCookies(cookies) {
  return [...cookies].map(([nombre, valor]) => `${nombre}=${valor}`).join('; ');
}

function tokenCsrf(html) {
  const coincidencia = html.match(/name="_token"[^>]*value="([^"]+)"/);
  if (!coincidencia) {
    throw new Error('No se encontró el token CSRF del panel.');
  }
  return coincidencia[1];
}

async function iniciarSesionPanel() {
  const cookies = new Map();
  const login = await fetch(`${PANEL}/login`);
  cookiesDe(login, cookies);
  const csrf = tokenCsrf(await login.text());
  const respuesta = await fetch(`${PANEL}/login`, {
    method: 'POST',
    redirect: 'manual',
    headers: {
      Cookie: encabezadoCookies(cookies),
      Referer: `${PANEL}/login`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({ _token: csrf, email: 'admin@crm.test', password: CLAVE }),
  });
  cookiesDe(respuesta, cookies);
  if (respuesta.status !== 302) {
    throw new Error(`El login del panel respondió ${respuesta.status}.`);
  }
  const pagina = await fetch(`${PANEL}/clientes`, {
    headers: { Cookie: encabezadoCookies(cookies) },
  });
  cookiesDe(pagina, cookies);
  return { cookies, csrf: tokenCsrf(await pagina.text()) };
}

async function iniciarSesionApi() {
  const respuesta = await fetch(`${API}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ correo: 'valeria@crm.test', clave: CLAVE }),
  });
  if (respuesta.status !== 200) {
    throw new Error(`El login de la API respondió ${respuesta.status}.`);
  }
  return (await respuesta.json()).token;
}

async function estadoFinal() {
  const conexion = await mysql.createConnection({
    host: process.env.DB_HOST,
    port: Number.parseInt(process.env.DB_PORT ?? '3306', 10),
    database: BASE,
    user: process.env.DB_USERNAME,
    password: process.env.DB_PASSWORD,
  });
  try {
    const [clientes] = await conexion.execute('SELECT vendedor_id FROM clientes WHERE id = ?', [CLIENTE_ID]);
    const [oportunidades] = await conexion.execute(
      "SELECT id, vendedor_id, etapa FROM oportunidades WHERE cliente_id = ? AND etapa NOT IN ('ganada', 'perdida')",
      [CLIENTE_ID],
    );
    return { vendedorId: clientes[0]?.vendedor_id ?? null, oportunidades };
  } finally {
    await conexion.end();
  }
}

async function ronda(numero) {
  const [token, panel] = await Promise.all([iniciarSesionApi(), iniciarSesionPanel()]);
  const inicio = Date.now();

  const crear = fetch(`${API}/api/oportunidades`, {
    method: 'POST',
    signal: AbortSignal.timeout(10000),
    headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ cliente_id: CLIENTE_ID, titulo: `S8 ronda ${numero}` }),
  });
  const liberar = fetch(`${PANEL}/clientes/${CLIENTE_ID}/liberar`, {
    method: 'PATCH',
    redirect: 'manual',
    signal: AbortSignal.timeout(10000),
    headers: {
      Cookie: encabezadoCookies(panel.cookies),
      Referer: `${PANEL}/clientes`,
      'X-CSRF-TOKEN': panel.csrf,
    },
  });
  const [respuestaApi, respuestaPanel] = await Promise.all([crear, liberar]);
  const estado = await estadoFinal();
  const apiBody = await respuestaApi.json();

  if (respuestaApi.status === 500 || respuestaPanel.status === 500) {
    throw new Error(`Ronda ${numero}: apareció HTTP 500.`);
  }
  if (![201, 422].includes(respuestaApi.status)) {
    throw new Error(`Ronda ${numero}: API respondió ${respuestaApi.status}.`);
  }
  if (respuestaPanel.status !== 302) {
    throw new Error(`Ronda ${numero}: panel respondió ${respuestaPanel.status}.`);
  }
  if (estado.vendedorId === null && estado.oportunidades.length !== 0) {
    throw new Error(`Ronda ${numero}: D3 se violó; cliente libre con oportunidad abierta.`);
  }
  if (estado.vendedorId !== null && estado.oportunidades.some((oportunidad) => oportunidad.vendedor_id !== estado.vendedorId)) {
    throw new Error(`Ronda ${numero}: D2 se violó; dueño de oportunidad abierta desalineado.`);
  }

  console.log(JSON.stringify({
    ronda: numero,
    milisegundos: Date.now() - inicio,
    api: { status: respuestaApi.status, error: apiBody.error ?? null },
    panel: { status: respuestaPanel.status },
    integridad: estado,
  }));
}

const rondas = Number.parseInt(process.env.RONDAS ?? '3', 10);
for (let numero = 1; numero <= rondas; numero += 1) {
  await ronda(numero);
}
