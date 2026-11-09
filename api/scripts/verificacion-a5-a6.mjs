import assert from 'node:assert/strict';
import mysql from 'mysql2/promise';

const API = process.env.API_ORIGIN ?? 'http://127.0.0.1:3000';
const CLAVE = process.env.CLAVE_SEMILLA;
if (process.env.DB_DATABASE !== 'crm_agua_test') {
  throw new Error('La verificación A5/A6 solo puede ejecutarse con DB_DATABASE=crm_agua_test.');
}
if (!CLAVE) {
  throw new Error('Defina CLAVE_SEMILLA en el entorno; no se guarda ninguna credencial en el script.');
}

const conexion = await mysql.createConnection({
  host: process.env.DB_HOST,
  port: Number.parseInt(process.env.DB_PORT ?? '3306', 10),
  database: process.env.DB_DATABASE,
  user: process.env.DB_USERNAME,
  password: process.env.DB_PASSWORD,
});

async function login(correo) {
  const respuesta = await fetch(`${API}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ correo, clave: CLAVE }),
  });
  assert.equal(respuesta.status, 200, `login ${correo}`);
  return (await respuesta.json()).token;
}

async function pedir(token, ruta, opciones = {}) {
  const respuesta = await fetch(`${API}/api${ruta}`, {
    ...opciones,
    headers: {
      Authorization: `Bearer ${token}`,
      ...(opciones.body === undefined ? {} : { 'Content-Type': 'application/json' }),
      ...(opciones.headers ?? {}),
    },
  });
  const cuerpo = respuesta.status === 204 ? null : await respuesta.json();
  return { status: respuesta.status, cuerpo };
}

function assertActividad(cuerpo) {
  assert.deepEqual(Object.keys(cuerpo).sort(), ['cliente', 'completada', 'descripcion', 'id', 'tipo', 'vence_en', 'vencida']);
  assert.deepEqual(Object.keys(cuerpo.cliente).sort(), ['id', 'nombre']);
}

function assertResumen(cuerpo) {
  assert.deepEqual(Object.keys(cuerpo).sort(), ['cartera', 'mes', 'pronostico', 'total', 'ventas']);
  assert.deepEqual(Object.keys(cuerpo.cartera).sort(), ['clientes', 'maximo']);
}

async function reasignarComercialADiego() {
  await conexion.execute('UPDATE clientes SET vendedor_id = ? WHERE id = ?', [5, 1]);
  await conexion.execute(
    "UPDATE oportunidades SET vendedor_id = ? WHERE cliente_id = ? AND etapa NOT IN ('ganada', 'perdida')",
    [5, 1],
  );
}

async function liberarMaria() {
  await conexion.execute('UPDATE clientes SET vendedor_id = NULL WHERE id = ?', [3]);
}

async function quitarActividadTemporal(id) {
  await conexion.execute('DELETE FROM actividades WHERE id = ?', [id]);
}

const valeria = await login('valeria@crm.test');
const diego = await login('diego@crm.test');

for (const ruta of ['/actividades', '/reportes/resumen']) {
  const respuesta = await fetch(`${API}/api${ruta}`);
  assert.equal(respuesta.status, 401, `JWT obligatorio en ${ruta}`);
}

// A5.1, A5.2, A5.7 y A5.8: cartera actual, filtros y vencida.
let resultado = await pedir(valeria, '/actividades?estado=pendiente');
assert.equal(resultado.status, 200);
assert.equal(resultado.cuerpo.length, 2);
assert.equal(resultado.cuerpo.find((actividad) => actividad.descripcion === 'Presentar opciones de soporte').vencida, true);
resultado.cuerpo.forEach(assertActividad);

resultado = await pedir(valeria, '/actividades?estado=otro');
assert.equal(resultado.status, 400);

resultado = await pedir(valeria, '/actividades');
assert.equal(resultado.status, 200);
assert.equal(resultado.cuerpo.length, 3);
resultado.cuerpo.forEach(assertActividad);

resultado = await pedir(valeria, '/actividades?estado=completada');
assert.equal(resultado.status, 200);
assert.equal(resultado.cuerpo.length, 1);
assert.equal(resultado.cuerpo[0].vencida, false);

// A5.3, A5.4, A5.5 y A5.6: alta, UTC, zona obligatoria y propiedad.
resultado = await pedir(valeria, '/clientes/2/actividades', {
  method: 'POST',
  body: JSON.stringify({ tipo: 'tarea', descripcion: 'Actividad de prueba', vence_en: '2026-12-01T10:00:00-06:00' }),
});
assert.equal(resultado.status, 201);
assert.equal(resultado.cuerpo.vence_en, '2026-12-01T16:00:00.000Z');
assertActividad(resultado.cuerpo);
const actividadTemporal = resultado.cuerpo.id;

resultado = await pedir(valeria, '/clientes/2/actividades', {
  method: 'POST',
  body: JSON.stringify({ tipo: 'tarea', descripcion: 'Sin zona', vence_en: '2026-12-01T10:00' }),
});
assert.equal(resultado.status, 422);
await quitarActividadTemporal(actividadTemporal);

resultado = await pedir(valeria, '/clientes/2/actividades', {
  method: 'POST',
  body: JSON.stringify({ tipo: 'visita', descripcion: '' }),
});
assert.equal(resultado.status, 422);

resultado = await pedir(valeria, '/clientes/2/actividades', {
  method: 'POST',
  body: JSON.stringify({ tipo: 'tarea', descripcion: 'x'.repeat(501) }),
});
assert.equal(resultado.status, 422);

resultado = await pedir(valeria, '/clientes/4/actividades', {
  method: 'POST',
  body: JSON.stringify({ tipo: 'llamada', descripcion: 'Ajena' }),
});
assert.equal(resultado.status, 404);
resultado = await pedir(valeria, '/actividades/4', {
  method: 'PATCH',
  body: JSON.stringify({ completada: true }),
});
assert.equal(resultado.status, 404);

// A5.9, A5.10 y A5.11: completar y desmarcar.
resultado = await pedir(valeria, '/actividades/2', {
  method: 'PATCH',
  body: JSON.stringify({ completada: true }),
});
assert.equal(resultado.status, 200);
assert.equal(resultado.cuerpo.completada, true);
assert.equal(resultado.cuerpo.vencida, false);

resultado = await pedir(valeria, '/actividades/2', {
  method: 'PATCH',
  body: JSON.stringify({ completada: 'si' }),
});
assert.equal(resultado.status, 422);
resultado = await pedir(valeria, '/actividades/2', {
  method: 'PATCH',
  body: JSON.stringify({}),
});
assert.equal(resultado.status, 422);

resultado = await pedir(valeria, '/actividades/1', {
  method: 'PATCH',
  body: JSON.stringify({ completada: false }),
});
assert.equal(resultado.status, 200);
assert.equal(resultado.cuerpo.vencida, true);

// A6.1, A6.2, A6.3 y A6.5 sobre la semilla intacta.
resultado = await pedir(valeria, '/reportes/resumen?mes=2026-10');
assert.equal(resultado.status, 200);
assertResumen(resultado.cuerpo);
assert.deepEqual(resultado.cuerpo, {
  mes: '2026-10', ventas: 2, total: 9250, pronostico: 2512.88,
  cartera: { clientes: 3, maximo: 10 },
});

resultado = await pedir(valeria, '/reportes/resumen?mes=2026-11');
assert.equal(resultado.status, 200);
assert.equal(resultado.cuerpo.ventas, 1);
assert.equal(resultado.cuerpo.total, 3600);

resultado = await pedir(diego, '/reportes/resumen?mes=2026-10');
assert.equal(resultado.status, 200);
assert.deepEqual(resultado.cuerpo, {
  mes: '2026-10', ventas: 1, total: 6000, pronostico: 112.5,
  cartera: { clientes: 2, maximo: 10 },
});

resultado = await pedir(valeria, '/reportes/resumen?mes=2026-13');
assert.equal(resultado.status, 400);
resultado = await pedir(valeria, '/reportes/resumen?mes=octubre');
assert.equal(resultado.status, 400);

// A6.4: sin parámetro usa el mes actual fijo de Guatemala y devuelve números JSON.
resultado = await pedir(valeria, '/reportes/resumen');
assert.equal(resultado.status, 200);
assert.match(resultado.cuerpo.mes, /^20[0-9]{2}-(0[1-9]|1[0-2])$/);
assert.equal(typeof resultado.cuerpo.total, 'number');
assert.equal(typeof resultado.cuerpo.pronostico, 'number');

// A5.12 y A5.13: reasignación conserva actividades y cambia la visibilidad actual.
await reasignarComercialADiego();
resultado = await pedir(diego, '/actividades');
assert.equal(resultado.status, 200);
assert.equal(resultado.cuerpo.length, 2);
assert.ok(resultado.cuerpo.some((actividad) => actividad.descripcion === 'Confirmar fecha de instalación'));

resultado = await pedir(valeria, '/actividades');
assert.equal(resultado.status, 200);
assert.equal(resultado.cuerpo.length, 2);

resultado = await pedir(diego, '/actividades/1', { method: 'PATCH', body: JSON.stringify({ completada: true }) });
assert.equal(resultado.status, 200);
resultado = await pedir(valeria, '/actividades/1', { method: 'PATCH', body: JSON.stringify({ completada: true }) });
assert.equal(resultado.status, 404);

// A6.6: después de L4.8, las abiertas pasan a Diego; las ventas cerradas siguen con Valeria.
resultado = await pedir(valeria, '/reportes/resumen?mes=2026-10');
assert.equal(resultado.status, 200);
assertResumen(resultado.cuerpo);
assert.deepEqual(resultado.cuerpo, {
  mes: '2026-10', ventas: 2, total: 9250, pronostico: 450,
  cartera: { clientes: 2, maximo: 10 },
});

resultado = await pedir(diego, '/reportes/resumen?mes=2026-10');
assert.equal(resultado.status, 200);
assert.deepEqual(resultado.cuerpo, {
  mes: '2026-10', ventas: 1, total: 6000, pronostico: 2175.38,
  cartera: { clientes: 3, maximo: 10 },
});

// Restablecer el estado de la actividad de A5.9 para comprobar A5.14 con la semilla pendiente.
resultado = await pedir(valeria, '/actividades/2', { method: 'PATCH', body: JSON.stringify({ completada: false }) });
assert.equal(resultado.status, 200);

// A5.14: cliente liberado deja de exponer sus actividades.
await liberarMaria();
resultado = await pedir(valeria, '/actividades?estado=pendiente');
assert.equal(resultado.status, 200);
assert.equal(resultado.cuerpo.length, 1);
assert.equal(resultado.cuerpo[0].descripcion, 'Enviar propuesta de capacitación');

console.log('A5.1-A5.14 y A6.1-A6.6: OK');
await conexion.end();
