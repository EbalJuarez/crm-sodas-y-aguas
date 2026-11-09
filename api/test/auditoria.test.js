import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { test } from 'node:test';

process.env.CORS_ORIGIN ??= 'http://127.0.0.1:5500';
process.env.JWT_SECRET ??= 'test-secret-that-is-at-least-32-characters';
process.env.DB_HOST ??= '127.0.0.1';
process.env.DB_PORT ??= '3306';
process.env.DB_DATABASE ??= 'crm_agua_test';
process.env.DB_USERNAME ??= 'test';
process.env.DB_PASSWORD ??= 'test';

const { default: app } = await import('../src/app.js');

function escuchar() {
  return new Promise((resolve, reject) => {
    const servidor = createServer(app);
    servidor.once('error', reject);
    servidor.listen(0, '127.0.0.1', () => resolve(servidor));
  });
}

function cerrar(servidor) {
  return new Promise((resolve, reject) => servidor.close((error) => (error ? reject(error) : resolve())));
}

async function pedir(servidor, ruta, opciones = {}) {
  const { port } = servidor.address();
  return fetch(`http://127.0.0.1:${port}${ruta}`, opciones);
}

test('las rutas protegidas sin JWT responden 401 JSON', async (t) => {
  const servidor = await escuchar();
  t.after(() => cerrar(servidor));

  for (const ruta of ['/api/clientes', '/api/productos', '/api/no-existe', '/api/auth/no-existe']) {
    const respuesta = await pedir(servidor, ruta);
    assert.equal(respuesta.status, 401, ruta);
    assert.deepEqual(await respuesta.json(), { error: 'No autorizado.' }, ruta);
  }
});

test('login vacío responde 422 con errores por campo y sin datos internos', async (t) => {
  const servidor = await escuchar();
  t.after(() => cerrar(servidor));

  const respuesta = await pedir(servidor, '/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: '{}',
  });

  assert.equal(respuesta.status, 422);
  assert.deepEqual(await respuesta.json(), {
    errores: { correo: 'Escriba su correo.', clave: 'Escriba su clave.' },
  });
});

test('el error 500 es genérico y no expone SQL', async () => {
  const respuesta = {
    status(codigo) { this.codigo = codigo; return this; },
    json(cuerpo) { this.cuerpo = cuerpo; return this; },
  };
  const { errorHandler } = await import('../src/middleware/errores.js');
  const original = console.error;
  console.error = () => {};
  try {
    errorHandler({ message: 'SELECT password FROM users', sqlMessage: 'secret SQL' }, null, respuesta, null);
  } finally {
    console.error = original;
  }

  assert.equal(respuesta.codigo, 500);
  assert.deepEqual(respuesta.cuerpo, { error: 'Ocurrió un error interno del servidor.' });
});
