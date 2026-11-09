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

test('cuerpo JSON mayor que 100 KB responde 413 JSON', async (t) => {
  const servidor = await escuchar();
  t.after(() => cerrar(servidor));

  const { port } = servidor.address();
  const respuesta = await fetch(`http://127.0.0.1:${port}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ correo: 'a@b.test', clave: 'x'.repeat(102400) }),
  });

  assert.equal(respuesta.status, 413);
  assert.deepEqual(await respuesta.json(), {
    error: 'El cuerpo de la petición supera el límite permitido de 100 KB.',
  });
});

test('JSON mal formado conserva 400 JSON', async (t) => {
  const servidor = await escuchar();
  t.after(() => cerrar(servidor));

  const { port } = servidor.address();
  const respuesta = await fetch(`http://127.0.0.1:${port}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: '{',
  });

  assert.equal(respuesta.status, 400);
  assert.deepEqual(await respuesta.json(), { error: 'El cuerpo JSON no tiene un formato válido.' });
});
