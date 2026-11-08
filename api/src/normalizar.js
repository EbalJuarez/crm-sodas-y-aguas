/**
 * Normalización de NIT y teléfono (R4, spec 3.4 y aclaración 1.1).
 *
 * Debe dar exactamente lo mismo que laravel/app/Support/Nit.php y Telefono.php (L7.2): las dos
 * puertas se prueban con el mismo archivo, docs/casos-normalizacion.json.
 */

/**
 * Compacta un NIT: mayúsculas (solo a–z, como strtoupper de PHP), sin espacios ni guiones y sin
 * ceros a la izquierda. No valida. También sirve para la búsqueda ?q= (P-8).
 */
export function compactarNit(valor) {
  const texto = typeof valor === 'string' ? valor : '';

  return texto
    .replace(/[a-z]/g, (letra) => letra.toUpperCase())
    .replace(/[\s-]+/gu, '')
    .replace(/^0+/, '');
}

/** `CF` (en cualquier forma) o un NIT de 2 a 12 caracteres con dígito verificador módulo 11. */
export function nitEsValido(valor) {
  const nit = compactarNit(valor);

  if (nit === 'CF') {
    return true;
  }

  if (!/^[0-9]{1,11}[0-9K]$/.test(nit)) {
    return false;
  }

  const cuerpo = nit.slice(0, -1);
  let suma = 0;
  let factor = 2;

  for (let indice = cuerpo.length - 1; indice >= 0; indice -= 1) {
    suma += Number(cuerpo[indice]) * factor;
    factor += 1;
  }

  const esperado = (11 - (suma % 11)) % 11;

  return nit.at(-1) === (esperado === 10 ? 'K' : String(esperado));
}

/**
 * NIT listo para guardar: el compacto, o null. Ojo: devuelve null tanto para CF como para un NIT
 * inválido (igual que Nit::normalizar), así que primero se llama a nitEsValido().
 */
export function normalizarNit(valor) {
  if (!nitEsValido(valor)) {
    return null;
  }

  const nit = compactarNit(valor);

  return nit === 'CF' ? null : nit;
}

/**
 * Teléfono de 8 dígitos, o null si es inválido. Regla de la aclaración 1.1:
 * 1. se quitan espacios, guiones, puntos y paréntesis;
 * 2. si empieza con +502, se quita ese prefijo;
 * 3. si no, y quedan exactamente 11 dígitos que empiezan con 502, se quita el 502
 *    (así 5021 2345 conserva su 502: es parte del número);
 * 4. el resultado debe cumplir ^[2-9][0-9]{7}$.
 */
export function normalizarTelefono(valor) {
  let telefono = (typeof valor === 'string' ? valor : '').replace(/[\s.()-]+/gu, '');

  if (telefono.startsWith('+502')) {
    telefono = telefono.slice(4);
  } else if (/^502[0-9]{8}$/.test(telefono)) {
    telefono = telefono.slice(3);
  }

  return /^[2-9][0-9]{7}$/.test(telefono) ? telefono : null;
}

export function telefonoEsValido(valor) {
  return normalizarTelefono(valor) !== null;
}
