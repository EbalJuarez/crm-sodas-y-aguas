// Recorre docs/casos-normalizacion.json, el mismo archivo que prueba el panel
// (laravel/tests/Feature/ClientesTest.php). Si una puerta acepta lo que la otra rechaza, falla (L7.2).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import {
  correoEsValido,
  nitEsValido,
  normalizarNit,
  normalizarTelefono,
  telefonoEsValido,
} from '../src/normalizar.js';

const casos = JSON.parse(
  readFileSync(new URL('../../docs/casos-normalizacion.json', import.meta.url), 'utf8'),
);

test('NIT: mismos resultados que Nit.php', () => {
  assert.ok(casos.nit.length > 0);
  for (const caso of casos.nit) {
    assert.equal(nitEsValido(caso.entrada), caso.valido, `esValido(${JSON.stringify(caso.entrada)})`);
    assert.equal(normalizarNit(caso.entrada), caso.normalizado, `normalizar(${JSON.stringify(caso.entrada)})`);
  }
});

test('Teléfono: mismos resultados que Telefono.php', () => {
  assert.ok(casos.telefono.length > 0);
  for (const caso of casos.telefono) {
    assert.equal(telefonoEsValido(caso.entrada), caso.valido, `esValido(${JSON.stringify(caso.entrada)})`);
    assert.equal(normalizarTelefono(caso.entrada), caso.normalizado, `normalizar(${JSON.stringify(caso.entrada)})`);
  }
});

test('Correo: mismos resultados que la regla del panel (email + Correo.php)', () => {
  assert.ok(casos.correo.length > 0);
  for (const caso of casos.correo) {
    assert.equal(correoEsValido(caso.entrada), caso.valido, `correo(${JSON.stringify(caso.entrada)})`);
  }
});

test('Valores que no son texto son inválidos (no lanzan)', () => {
  for (const valor of [undefined, null, 4521817, ['4521817K'], {}]) {
    assert.equal(nitEsValido(valor), false);
    assert.equal(telefonoEsValido(valor), false);
    assert.equal(correoEsValido(valor), false);
  }
});
