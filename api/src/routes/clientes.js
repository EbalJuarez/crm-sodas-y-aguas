import { Router } from 'express';
import pool, { transaccion } from '../db.js';
import { exigirCuerpo } from '../middleware/cuerpo.js';
import { compactarNit, nitEsValido, normalizarNit, normalizarTelefono } from '../normalizar.js';

/**
 * A3 — Clientes y cartera (spec 3.1, 3.1.1, 3.4; R2, R3, R4, D5, P-7, P-8).
 *
 * - El dueño sale siempre de request.usuario.id (token), nunca del cuerpo (R2, D5).
 * - Lo ajeno y lo inexistente responden el mismo 404, filtrando por dueño en el WHERE (S5).
 * - Todo el SQL es fijo y con parámetros `?` (S3). Los únicos fragmentos que se arman son
 *   constantes de este archivo (columnas de una lista blanca), nunca texto del usuario.
 */

const router = Router();

const MAXIMO_CARTERA = 10;

const MENSAJES = {
  noEncontrado: 'No se encontró el cliente.',
  idInvalido: 'El identificador del cliente no es válido.',
  cuerpoNoObjeto: 'El cuerpo debe ser un objeto JSON.',
  sinCampos: 'Envíe al menos uno de estos campos: nombre, nit, telefono, correo.',
  qInvalido: 'El parámetro q debe ser un solo texto.',
  carteraLlena: 'Su cartera ya tiene 10 clientes, el máximo permitido.',
  yaTomado: 'Este cliente ya no está disponible.',
  nitRepetido: 'Ya existe un cliente con ese NIT.',
  telefonoRepetido: 'Ya existe un cliente con ese teléfono.',
  repetido: 'Ya existe un cliente con esos datos.',
  cuentaInvalida: 'No autorizado.',
  // Spec 3.4 (mismos textos que GuardarClienteRequest del panel).
  nombre: 'Escriba el nombre del cliente.',
  nombreLargo: 'El nombre puede tener hasta 150 caracteres.',
  nit: 'Escriba un NIT válido (con su dígito verificador) o CF.',
  telefono: 'Escriba un teléfono de Guatemala de 8 dígitos.',
  correo: 'Escriba un correo válido.',
  correoLargo: 'El correo puede tener hasta 150 caracteres.',
};

// Columnas que el contrato expone (nunca vendedor_id) y las que PATCH puede editar (D5).
const COLUMNAS_CLIENTE = 'id, nombre, nit, telefono, correo';
const CAMPOS_EDITABLES = ['nombre', 'nit', 'telefono', 'correo'];

// Aproximación de la regla `email` (RFC) de Laravel: partes «dot-atom» separadas por una @.
const ATOMO = '[^\\s@"(),.:;<>\\[\\]\\\\]+';
const PATRON_CORREO = new RegExp(`^${ATOMO}(?:\\.${ATOMO})*@${ATOMO}(?:\\.${ATOMO})*$`, 'u');

/** Forma `Cliente` de 3.2: NIT NULL se devuelve como "CF". */
function aCliente(fila) {
  return {
    id: fila.id,
    nombre: fila.nombre,
    nit: fila.nit ?? 'CF',
    telefono: fila.telefono,
    correo: fila.correo,
  };
}

function longitud(texto) {
  return [...texto].length; // caracteres, no unidades UTF-16 (como mb_strlen / max:150)
}

// Validadores por campo (3.4). Devuelven { valor } o { error }.
const VALIDADORES = {
  nombre(valor) {
    if (typeof valor !== 'string' || valor.trim() === '') {
      return { error: MENSAJES.nombre };
    }
    const nombre = valor.trim();

    return longitud(nombre) > 150 ? { error: MENSAJES.nombreLargo } : { valor: nombre };
  },
  nit(valor) {
    if (typeof valor !== 'string' || !nitEsValido(valor)) {
      return { error: MENSAJES.nit };
    }

    return { valor: normalizarNit(valor) }; // CF → null (R4)
  },
  telefono(valor) {
    const telefono = typeof valor === 'string' ? normalizarTelefono(valor) : null;

    return telefono === null ? { error: MENSAJES.telefono } : { valor: telefono };
  },
  correo(valor) {
    // Opcional: ausente, null o "" (como ConvertEmptyStringsToNull de Laravel) se guarda NULL.
    if (valor === undefined || valor === null) {
      return { valor: null };
    }
    if (typeof valor !== 'string') {
      return { error: MENSAJES.correo };
    }
    const correo = valor.trim();
    if (correo === '') {
      return { valor: null };
    }
    if (!PATRON_CORREO.test(correo)) {
      return { error: MENSAJES.correo };
    }

    return longitud(correo) > 150 ? { error: MENSAJES.correoLargo } : { valor: correo };
  },
};

/** Valida los campos indicados; devuelve { datos } o { errores } (un mensaje por campo). */
function validar(cuerpo, campos) {
  const datos = {};
  const errores = {};

  for (const campo of campos) {
    const resultado = VALIDADORES[campo](cuerpo[campo]);
    if ('error' in resultado) {
      errores[campo] = resultado.error;
    } else {
      datos[campo] = resultado.valor;
    }
  }

  return Object.keys(errores).length > 0 ? { errores } : { datos };
}

function esObjeto(valor) {
  return typeof valor === 'object' && valor !== null && !Array.isArray(valor);
}

/** ER_DUP_ENTRY (1062) → 409. El índice único es el árbitro final de los duplicados (R4, S8). */
function respuestaDuplicado(error) {
  if (error?.errno !== 1062) {
    return null;
  }
  const mensaje = String(error.sqlMessage ?? error.message ?? '');
  if (mensaje.includes('clientes_nit_unique')) {
    return { estado: 409, cuerpo: { error: MENSAJES.nitRepetido } };
  }
  if (mensaje.includes('clientes_telefono_unique')) {
    return { estado: 409, cuerpo: { error: MENSAJES.telefonoRepetido } };
  }

  return { estado: 409, cuerpo: { error: MENSAJES.repetido } };
}

/**
 * R3: bloquea la fila del vendedor en users y cuenta su cartera, dentro de la transacción.
 * Mismo orden que el panel (asignar, liberar): primero users, después clientes.
 *
 * Con REPEATABLE READ la instantánea se toma en la primera lectura no bloqueante, que aquí es el
 * COUNT, ya con el bloqueo obtenido: ve lo que confirmó quien tenía el bloqueo antes.
 * Devuelve el número de clientes, o null si la cuenta dejó de ser un vendedor activo.
 */
async function bloquearYContarCartera(conexion, vendedorId) {
  const [vendedores] = await conexion.execute(
    "SELECT id FROM users WHERE id = ? AND rol = 'vendedor' AND activo = 1 FOR UPDATE",
    [vendedorId],
  );
  if (vendedores.length === 0) {
    return null;
  }

  const [filas] = await conexion.execute(
    'SELECT COUNT(*) AS total FROM clientes WHERE vendedor_id = ?',
    [vendedorId],
  );

  return Number(filas[0].total);
}

function enviar(response, { estado, cuerpo }) {
  return response.status(estado).json(cuerpo);
}

// `:id` debe ser numérico (3.3: un :id de ruta no numérico → 400).
router.param('id', (request, response, next, valor) => {
  if (!/^[0-9]{1,20}$/.test(valor)) {
    return response.status(400).json({ error: MENSAJES.idInvalido });
  }

  return next();
});

// GET /clientes?q= — su cartera; q busca «contiene» en nombre (tal cual) o en NIT compactado (P-8).
router.get('/', async (request, response) => {
  const { q } = request.query;
  // ?q=a&q=b llega como arreglo; ?q[]=x o ?q[a]=x, como otra llave: los dos son 400 (nunca 500).
  const qConForma = Object.keys(request.query).some((llave) => llave.startsWith('q['));
  if ((q !== undefined && typeof q !== 'string') || qConForma) {
    return response.status(400).json({ error: MENSAJES.qInvalido });
  }

  const busqueda = (q ?? '').trim();
  const vendedorId = request.usuario.id;
  let filas;

  if (busqueda === '') {
    [filas] = await pool.execute(
      `SELECT ${COLUMNAS_CLIENTE} FROM clientes WHERE vendedor_id = ? ORDER BY nombre, id`,
      [vendedorId],
    );
  } else {
    // % y _ (y el propio !) se escapan para que el LIKE los tome como texto.
    const comoTexto = (texto) => `%${texto.replace(/[!%_]/g, '!$&')}%`;
    const nit = compactarNit(busqueda);

    if (nit === '') {
      [filas] = await pool.execute(
        `SELECT ${COLUMNAS_CLIENTE} FROM clientes
          WHERE vendedor_id = ? AND nombre LIKE ? ESCAPE '!'
          ORDER BY nombre, id`,
        [vendedorId, comoTexto(busqueda)],
      );
    } else {
      [filas] = await pool.execute(
        `SELECT ${COLUMNAS_CLIENTE} FROM clientes
          WHERE vendedor_id = ? AND (nombre LIKE ? ESCAPE '!' OR nit LIKE ? ESCAPE '!')
          ORDER BY nombre, id`,
        [vendedorId, comoTexto(busqueda), comoTexto(nit)],
      );
    }
  }

  return response.status(200).json(filas.map(aCliente));
});

// GET /clientes/disponibles — solo { id, nombre } (R3). Va antes de /:id.
router.get('/disponibles', async (_request, response) => {
  const [filas] = await pool.execute(
    'SELECT id, nombre FROM clientes WHERE vendedor_id IS NULL ORDER BY nombre, id',
  );

  return response.status(200).json(filas.map((fila) => ({ id: fila.id, nombre: fila.nombre })));
});

// POST /clientes — alta directa en su cartera (máximo 10).
router.post('/', exigirCuerpo, async (request, response) => {
  if (!esObjeto(request.body)) {
    return response.status(400).json({ error: MENSAJES.cuerpoNoObjeto });
  }

  const resultado = validar(request.body, CAMPOS_EDITABLES);
  if (resultado.errores) {
    return response.status(422).json({ errores: resultado.errores });
  }
  const { nombre, nit, telefono, correo } = resultado.datos;
  const vendedorId = request.usuario.id;

  try {
    const respuesta = await transaccion(async (conexion) => {
      const cartera = await bloquearYContarCartera(conexion, vendedorId);
      if (cartera === null) {
        return { estado: 401, cuerpo: { error: MENSAJES.cuentaInvalida } };
      }
      if (cartera >= MAXIMO_CARTERA) {
        return { estado: 409, cuerpo: { error: MENSAJES.carteraLlena } };
      }

      const [insercion] = await conexion.execute(
        `INSERT INTO clientes (vendedor_id, nombre, nit, telefono, correo, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())`,
        [vendedorId, nombre, nit, telefono, correo],
      );

      return {
        estado: 201,
        cuerpo: aCliente({ id: insercion.insertId, nombre, nit, telefono, correo }),
      };
    });

    return enviar(response, respuesta);
  } catch (error) {
    const duplicado = respuestaDuplicado(error);
    if (duplicado) {
      return enviar(response, duplicado);
    }
    throw error;
  }
});

// POST /clientes/:id/tomar — atómico: UPDATE condicional + affectedRows, con la cartera bloqueada.
router.post('/:id/tomar', async (request, response) => {
  const clienteId = request.params.id;
  const vendedorId = request.usuario.id;

  const respuesta = await transaccion(async (conexion) => {
    const cartera = await bloquearYContarCartera(conexion, vendedorId);
    if (cartera === null) {
      return { estado: 401, cuerpo: { error: MENSAJES.cuentaInvalida } };
    }

    const [existe] = await conexion.execute('SELECT id FROM clientes WHERE id = ?', [clienteId]);
    if (existe.length === 0) {
      return { estado: 404, cuerpo: { error: MENSAJES.noEncontrado } };
    }
    if (cartera >= MAXIMO_CARTERA) {
      return { estado: 409, cuerpo: { error: MENSAJES.carteraLlena } };
    }

    // Solo una toma simultánea encuentra vendedor_id IS NULL; las demás ven 0 filas (409).
    // Un cliente que ya está en su propia cartera también responde 409 (A3.10: ya asignado).
    const [actualizacion] = await conexion.execute(
      `UPDATE clientes SET vendedor_id = ?, updated_at = UTC_TIMESTAMP()
        WHERE id = ? AND vendedor_id IS NULL`,
      [vendedorId, clienteId],
    );
    if (actualizacion.affectedRows === 0) {
      return { estado: 409, cuerpo: { error: MENSAJES.yaTomado } };
    }

    const [filas] = await conexion.execute(
      `SELECT ${COLUMNAS_CLIENTE} FROM clientes WHERE id = ? AND vendedor_id = ?`,
      [clienteId, vendedorId],
    );

    return { estado: 200, cuerpo: aCliente(filas[0]) };
  });

  return enviar(response, respuesta);
});

// GET /clientes/:id — solo de su cartera; ajeno, disponible o inexistente: 404.
router.get('/:id', async (request, response) => {
  const [filas] = await pool.execute(
    `SELECT ${COLUMNAS_CLIENTE} FROM clientes WHERE id = ? AND vendedor_id = ?`,
    [request.params.id, request.usuario.id],
  );
  if (filas.length === 0) {
    return response.status(404).json({ error: MENSAJES.noEncontrado });
  }

  return response.status(200).json(aCliente(filas[0]));
});

// PATCH /clientes/:id — orden: 404 (autorizar) → 400 sin campos editables (P-7) → 422 → 409.
router.patch('/:id', exigirCuerpo, async (request, response) => {
  const clienteId = request.params.id;
  const vendedorId = request.usuario.id;

  const [propio] = await pool.execute(
    'SELECT id FROM clientes WHERE id = ? AND vendedor_id = ?',
    [clienteId, vendedorId],
  );
  if (propio.length === 0) {
    return response.status(404).json({ error: MENSAJES.noEncontrado });
  }

  // D5: solo se leen los campos del contrato; el resto (p. ej. vendedor_id) se ignora.
  const cuerpo = esObjeto(request.body) ? request.body : {};
  const campos = CAMPOS_EDITABLES.filter((campo) => Object.hasOwn(cuerpo, campo));
  if (campos.length === 0) {
    return response.status(400).json({ error: MENSAJES.sinCampos });
  }

  const resultado = validar(cuerpo, campos);
  if (resultado.errores) {
    return response.status(422).json({ errores: resultado.errores });
  }

  // Los nombres de columna salen de CAMPOS_EDITABLES (constantes), no de las llaves del cuerpo.
  const asignaciones = campos.map((campo) => `${campo} = ?`).join(', ');
  const valores = campos.map((campo) => resultado.datos[campo]);

  try {
    const [actualizacion] = await pool.execute(
      `UPDATE clientes SET ${asignaciones}, updated_at = UTC_TIMESTAMP()
        WHERE id = ? AND vendedor_id = ?`,
      [...valores, clienteId, vendedorId],
    );
    // Si el panel lo reasignó entre la lectura y el UPDATE, ya no es suyo.
    if (actualizacion.affectedRows === 0) {
      return response.status(404).json({ error: MENSAJES.noEncontrado });
    }
  } catch (error) {
    const duplicado = respuestaDuplicado(error);
    if (duplicado) {
      return enviar(response, duplicado);
    }
    throw error;
  }

  const [filas] = await pool.execute(
    `SELECT ${COLUMNAS_CLIENTE} FROM clientes WHERE id = ? AND vendedor_id = ?`,
    [clienteId, vendedorId],
  );
  if (filas.length === 0) {
    return response.status(404).json({ error: MENSAJES.noEncontrado });
  }

  return response.status(200).json(aCliente(filas[0]));
});

export default router;
