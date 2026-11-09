import { Router } from 'express';
import pool, { transaccion } from '../db.js';
import { exigirCuerpo } from '../middleware/cuerpo.js';

/**
 * A4 — Oportunidades, líneas y etapas (spec 3.1, 3.1.1, 3.2; R5, R6, D1, D5; regla propia, sección 6).
 *
 * - El dueño es siempre request.usuario.id (token). Lo ajeno o inexistente: 404, filtrando por
 *   o.vendedor_id en el WHERE, antes de validar el cuerpo.
 * - Toda modificación toma, en una transacción, SELECT … FOR UPDATE de la oportunidad y decide
 *   con el estado leído ahí: dos cambios simultáneos no dejan dos registros de historial (A4.14),
 *   y una oportunidad cerrada responde 409 a cualquier modificación (A4.12).
 * - Orden de respuestas: 404 → 422 de formato del cuerpo → (con el bloqueo) 409 de estado →
 *   422 que dependen del estado (ganar sin productos, venta por caja contra la copia de la línea).
 * - Precio y unidades_por_caja se copian del catálogo en el mismo INSERT … SELECT (R6, RP).
 */

const router = Router();

const ETAPAS = ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida'];
const CERRADAS = ['ganada', 'perdida'];
const PROBABILIDAD = {
  prospecto: 10, calificado: 25, propuesta: 50, negociacion: 75, ganada: 100, perdida: 0,
};
// D1: avanzar una etapa entre las abiertas, ganar solo desde negociacion, perder desde cualquier abierta.
const TRANSICIONES = {
  prospecto: ['calificado', 'perdida'],
  calificado: ['propuesta', 'perdida'],
  propuesta: ['negociacion', 'perdida'],
  negociacion: ['ganada', 'perdida'],
  ganada: [],
  perdida: [],
};

const MENSAJES = {
  noEncontrada: 'No se encontró la oportunidad.',
  lineaNoEncontrada: 'No se encontró el producto en esta oportunidad.',
  idInvalido: 'El identificador no es válido.',
  etapaQuery: 'El filtro etapa debe ser prospecto, calificado, propuesta, negociacion, ganada o perdida.',
  cerrada: 'La oportunidad está cerrada y ya no se puede modificar.',
  transicion: 'Ese cambio de etapa no está permitido.',
  repetido: 'Este producto ya está en la oportunidad. Cambie su cantidad.',
  cuentaInvalida: 'No autorizado.',
  // Spec 3.4 y 3.1.1.
  titulo: 'Escriba el título.',
  tituloLargo: 'El título puede tener hasta 150 caracteres.',
  clienteId: 'Elija un cliente de su cartera.',
  productoId: 'Elija un producto activo del catálogo.',
  cantidad: 'La cantidad debe ser un número entero de 1 a 999.',
  etapa: 'Elija una etapa: prospecto, calificado, propuesta, negociacion, ganada o perdida.',
  ganarSinProductos: 'Agregue al menos un producto antes de ganar la oportunidad.',
  motivo: 'Escriba el motivo de la pérdida.',
  motivoLargo: 'El motivo puede tener hasta 255 caracteres.',
  porCaja: (unidades) => `Este producto se vende por caja de ${unidades} unidades: la cantidad debe ser múltiplo de ${unidades}.`,
  porCajaGenerico: 'La cantidad no cumple la venta por caja completa de este producto.',
};

// Total de una oportunidad = Σ cantidad × precio_unitario de sus líneas (precio guardado), en SQL.
const SQL_RESUMEN = `
  SELECT o.id, o.titulo, o.etapa, o.fecha_cierre, o.motivo_perdida,
         c.id AS cliente_id, c.nombre AS cliente_nombre,
         (SELECT COALESCE(SUM(op.cantidad * op.precio_unitario), 0)
            FROM oportunidad_productos op WHERE op.oportunidad_id = o.id) AS total
    FROM oportunidades o
    JOIN clientes c ON c.id = o.cliente_id`;

// ---------------------------------------------------------------------------------------------
// Formas de respuesta (3.2). Montos ya llegan como número (decimalNumbers) y fechas como Date (UTC).

function aResumen(fila) {
  return {
    id: fila.id,
    cliente: { id: fila.cliente_id, nombre: fila.cliente_nombre },
    titulo: fila.titulo,
    etapa: fila.etapa,
    probabilidad: PROBABILIDAD[fila.etapa],
    total: fila.total,
    fecha_cierre: fila.fecha_cierre ? fila.fecha_cierre.toISOString() : null,
    motivo_perdida: fila.motivo_perdida,
  };
}

/** DetalleOportunidad de una oportunidad propia, o null. `conexion` puede ser el pool. */
async function cargarDetalle(conexion, oportunidadId, vendedorId) {
  const [filas] = await conexion.execute(
    `${SQL_RESUMEN} WHERE o.id = ? AND o.vendedor_id = ?`,
    [oportunidadId, vendedorId],
  );
  if (filas.length === 0) {
    return null;
  }

  const [lineas] = await conexion.execute(
    `SELECT op.id, op.producto_id, p.nombre, op.cantidad, op.precio_unitario,
            op.cantidad * op.precio_unitario AS subtotal
       FROM oportunidad_productos op
       JOIN productos p ON p.id = op.producto_id
      WHERE op.oportunidad_id = ?
      ORDER BY op.id`,
    [oportunidadId],
  );
  const [historial] = await conexion.execute(
    `SELECT h.etapa_anterior, h.etapa_nueva, u.name AS usuario, h.created_at AS fecha
       FROM historial_etapas h
       JOIN users u ON u.id = h.usuario_id
      WHERE h.oportunidad_id = ?
      ORDER BY h.created_at, h.id`,
    [oportunidadId],
  );

  return {
    ...aResumen(filas[0]),
    productos: lineas.map((linea) => ({
      id: linea.id,
      producto_id: linea.producto_id,
      nombre: linea.nombre,
      cantidad: linea.cantidad,
      precio_unitario: linea.precio_unitario,
      subtotal: linea.subtotal,
    })),
    historial: historial.map((registro) => ({
      etapa_anterior: registro.etapa_anterior,
      etapa_nueva: registro.etapa_nueva,
      usuario: registro.usuario,
      fecha: registro.fecha.toISOString(),
    })),
  };
}

// ---------------------------------------------------------------------------------------------
// Validación de campos del cuerpo (3.4). Devuelven el valor limpio o un mensaje en `error`.

function cuerpoDe(request) {
  const cuerpo = request.body;

  return typeof cuerpo === 'object' && cuerpo !== null && !Array.isArray(cuerpo) ? cuerpo : {};
}

function validarTexto(valor, maximo, mensaje, mensajeLargo) {
  if (typeof valor !== 'string' || valor.trim() === '') {
    return { error: mensaje };
  }
  const texto = valor.trim();

  return [...texto].length > maximo ? { error: mensajeLargo } : { valor: texto };
}

function esIdPositivo(valor) {
  return Number.isInteger(valor) && valor > 0;
}

function validarCantidad(valor) {
  return Number.isInteger(valor) && valor >= 1 && valor <= 999 ? { valor } : { error: MENSAJES.cantidad };
}

function noEncontrada(response) {
  return response.status(404).json({ error: MENSAJES.noEncontrada });
}

function enviar(response, { estado, cuerpo }) {
  return cuerpo === undefined ? response.status(estado).end() : response.status(estado).json(cuerpo);
}

/** 404 temprano (antes de validar): ¿la oportunidad existe y es del vendedor? */
async function esPropia(oportunidadId, vendedorId) {
  const [filas] = await pool.execute(
    'SELECT id FROM oportunidades WHERE id = ? AND vendedor_id = ?',
    [oportunidadId, vendedorId],
  );

  return filas.length > 0;
}

/**
 * Bloquea la oportunidad propia dentro de la transacción y devuelve su etapa actual, o una
 * respuesta 404 (ya no es suya) o 409 (cerrada).
 */
async function bloquearAbierta(conexion, oportunidadId, vendedorId) {
  const [filas] = await conexion.execute(
    'SELECT id, etapa FROM oportunidades WHERE id = ? AND vendedor_id = ? FOR UPDATE',
    [oportunidadId, vendedorId],
  );
  if (filas.length === 0) {
    return { respuesta: { estado: 404, cuerpo: { error: MENSAJES.noEncontrada } } };
  }
  if (CERRADAS.includes(filas[0].etapa)) {
    return { respuesta: { estado: 409, cuerpo: { error: MENSAJES.cerrada } } };
  }

  return { etapa: filas[0].etapa };
}

/** Errores de la base que son reglas del negocio: 1062 (repetido) y 3819 (CHECK de venta por caja). */
function respuestaDeError(error) {
  if (error?.errno === 1062) {
    return { estado: 409, cuerpo: { error: MENSAJES.repetido } };
  }
  if (error?.errno === 3819 && String(error.sqlMessage ?? '').includes('caja_completa')) {
    return { estado: 422, cuerpo: { errores: { cantidad: MENSAJES.porCajaGenerico } } };
  }

  return null;
}

async function conErroresDeLaBase(response, trabajo) {
  try {
    return enviar(response, await transaccion(trabajo));
  } catch (error) {
    const respuesta = respuestaDeError(error);
    if (respuesta) {
      return enviar(response, respuesta);
    }
    throw error;
  }
}

// `:id` y `:lineaId` numéricos (3.3: un :id de ruta no numérico → 400).
for (const nombre of ['id', 'lineaId']) {
  router.param(nombre, (_request, response, next, valor) => (
    /^[0-9]{1,20}$/.test(valor) ? next() : response.status(400).json({ error: MENSAJES.idInvalido })
  ));
}

// ---------------------------------------------------------------------------------------------
// GET /oportunidades?etapa= — sus oportunidades (dueño = oportunidades.vendedor_id, D2), sin líneas.
router.get('/', async (request, response) => {
  const { etapa } = request.query;
  const etapaConForma = Object.keys(request.query).some((llave) => llave.startsWith('etapa['));
  if (etapaConForma || (etapa !== undefined && !ETAPAS.includes(etapa))) {
    return response.status(400).json({ error: MENSAJES.etapaQuery });
  }

  const [filas] = etapa === undefined
    ? await pool.execute(`${SQL_RESUMEN} WHERE o.vendedor_id = ? ORDER BY o.id`, [request.usuario.id])
    : await pool.execute(
      `${SQL_RESUMEN} WHERE o.vendedor_id = ? AND o.etapa = ? ORDER BY o.id`,
      [request.usuario.id, etapa],
    );

  return response.status(200).json(filas.map(aResumen));
});

// POST /oportunidades — { cliente_id, titulo }; nace en prospecto con un registro de historial.
router.post('/', exigirCuerpo, async (request, response) => {
  const cuerpo = cuerpoDe(request); // D5: etapa, vendedor_id, etc. se ignoran (A4.3)
  const errores = {};
  const titulo = validarTexto(cuerpo.titulo, 150, MENSAJES.titulo, MENSAJES.tituloLargo);
  if (titulo.error) {
    errores.titulo = titulo.error;
  }
  if (!esIdPositivo(cuerpo.cliente_id)) {
    errores.cliente_id = MENSAJES.clienteId;
  }
  if (Object.keys(errores).length > 0) {
    return response.status(422).json({ errores });
  }

   const vendedorId = request.usuario.id;

  return conErroresDeLaBase(response, async (conexion) => {
    // El panel bloquea users antes de clientes al liberar o reasignar. Mantener el mismo
    // orden aquí evita el ciclo users -> clientes / clientes -> users entre las dos puertas.
    const [vendedores] = await conexion.execute(
      "SELECT id FROM users WHERE id = ? AND rol = 'vendedor' AND activo = 1 FOR UPDATE",
      [vendedorId],
    );
    if (vendedores.length === 0) {
      return { estado: 401, cuerpo: { error: MENSAJES.cuentaInvalida } };
    }

    // Sección 4: el cliente debe ser de su cartera; se bloquea su fila para que el panel no lo
    // libere a la vez (D3). Ajeno, disponible o inexistente: el mismo 422 (A4.2).
    const [clientes] = await conexion.execute(
      'SELECT id FROM clientes WHERE id = ? AND vendedor_id = ? FOR UPDATE',
      [cuerpo.cliente_id, vendedorId],
    );
    if (clientes.length === 0) {
      return { estado: 422, cuerpo: { errores: { cliente_id: MENSAJES.clienteId } } };
    }

    const [insercion] = await conexion.execute(
      `INSERT INTO oportunidades (cliente_id, vendedor_id, titulo, etapa, created_at, updated_at)
       VALUES (?, ?, ?, 'prospecto', UTC_TIMESTAMP(), UTC_TIMESTAMP())`,
      [cuerpo.cliente_id, vendedorId, titulo.valor],
    );
    await conexion.execute(
      `INSERT INTO historial_etapas (oportunidad_id, usuario_id, etapa_anterior, etapa_nueva, created_at)
       VALUES (?, ?, NULL, 'prospecto', UTC_TIMESTAMP())`,
      [insercion.insertId, vendedorId],
    );

    return { estado: 201, cuerpo: await cargarDetalle(conexion, insercion.insertId, vendedorId) };
  });
});

// GET /oportunidades/:id
router.get('/:id', async (request, response) => {
  const detalle = await cargarDetalle(pool, request.params.id, request.usuario.id);

  return detalle ? response.status(200).json(detalle) : noEncontrada(response);
});

// PATCH /oportunidades/:id — solo { titulo }.
router.patch('/:id', exigirCuerpo, async (request, response) => {
  const { id } = request.params;
  const vendedorId = request.usuario.id;
  if (!(await esPropia(id, vendedorId))) {
    return noEncontrada(response);
  }

  const titulo = validarTexto(cuerpoDe(request).titulo, 150, MENSAJES.titulo, MENSAJES.tituloLargo);
  if (titulo.error) {
    return response.status(422).json({ errores: { titulo: titulo.error } });
  }

  return conErroresDeLaBase(response, async (conexion) => {
    const bloqueo = await bloquearAbierta(conexion, id, vendedorId);
    if (bloqueo.respuesta) {
      return bloqueo.respuesta;
    }

    await conexion.execute(
      'UPDATE oportunidades SET titulo = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
      [titulo.valor, id],
    );

    return { estado: 200, cuerpo: await cargarDetalle(conexion, id, vendedorId) };
  });
});

// PATCH /oportunidades/:id/etapa — { etapa, motivo_perdida? }: una transacción con el historial.
router.patch('/:id/etapa', exigirCuerpo, async (request, response) => {
  const { id } = request.params;
  const vendedorId = request.usuario.id;
  if (!(await esPropia(id, vendedorId))) {
    return noEncontrada(response);
  }

  const cuerpo = cuerpoDe(request);
  if (typeof cuerpo.etapa !== 'string' || !ETAPAS.includes(cuerpo.etapa)) {
    return response.status(422).json({ errores: { etapa: MENSAJES.etapa } }); // A4.17
  }
  const nueva = cuerpo.etapa;

  // El motivo solo se lee al perder (3.1); en cualquier otro cambio se ignora.
  let motivo = null;
  if (nueva === 'perdida') {
    const validado = validarTexto(cuerpo.motivo_perdida, 255, MENSAJES.motivo, MENSAJES.motivoLargo);
    if (validado.error) {
      return response.status(422).json({ errores: { motivo_perdida: validado.error } }); // A4.11
    }
    motivo = validado.valor;
  }

  return conErroresDeLaBase(response, async (conexion) => {
    // La etapa actual se lee CON el bloqueo: el segundo de dos cambios simultáneos espera aquí,
    // lee la etapa ya cambiada y recibe 409, sin escribir historial (A4.14).
    const bloqueo = await bloquearAbierta(conexion, id, vendedorId);
    if (bloqueo.respuesta) {
      return bloqueo.respuesta;
    }
    const actual = bloqueo.etapa;

    if (!TRANSICIONES[actual].includes(nueva)) {
      return { estado: 409, cuerpo: { error: MENSAJES.transicion } }; // D1, A4.13
    }

    if (nueva === 'ganada') {
      const [lineas] = await conexion.execute(
        'SELECT COUNT(*) AS cantidad FROM oportunidad_productos WHERE oportunidad_id = ?',
        [id],
      );
      if (Number(lineas[0].cantidad) === 0) {
        return { estado: 422, cuerpo: { errores: { etapa: MENSAJES.ganarSinProductos } } }; // A4.10
      }
    }

    if (CERRADAS.includes(nueva)) {
      await conexion.execute(
        `UPDATE oportunidades
            SET etapa = ?, motivo_perdida = ?, fecha_cierre = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
          WHERE id = ?`,
        [nueva, motivo, id],
      );
    } else {
      await conexion.execute(
        'UPDATE oportunidades SET etapa = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
        [nueva, id],
      );
    }

    await conexion.execute(
      `INSERT INTO historial_etapas (oportunidad_id, usuario_id, etapa_anterior, etapa_nueva, created_at)
       VALUES (?, ?, ?, ?, UTC_TIMESTAMP())`,
      [id, vendedorId, actual, nueva],
    );

    return { estado: 200, cuerpo: await cargarDetalle(conexion, id, vendedorId) };
  });
});

// POST /oportunidades/:id/productos — { producto_id, cantidad }; precio y caja se copian del catálogo.
router.post('/:id/productos', exigirCuerpo, async (request, response) => {
  const { id } = request.params;
  const vendedorId = request.usuario.id;
  if (!(await esPropia(id, vendedorId))) {
    return noEncontrada(response);
  }

  const cuerpo = cuerpoDe(request); // D5: precio_unitario se ignora (A4.7)
  const errores = {};
  if (!esIdPositivo(cuerpo.producto_id)) {
    errores.producto_id = MENSAJES.productoId;
  }
  const cantidad = validarCantidad(cuerpo.cantidad);
  if (cantidad.error) {
    errores.cantidad = cantidad.error;
  }
  if (Object.keys(errores).length > 0) {
    return response.status(422).json({ errores });
  }

  return conErroresDeLaBase(response, async (conexion) => {
    const bloqueo = await bloquearAbierta(conexion, id, vendedorId);
    if (bloqueo.respuesta) {
      return bloqueo.respuesta;
    }

    // FOR SHARE: el catálogo no cambia entre esta validación y la copia del INSERT … SELECT.
    const [productos] = await conexion.execute(
      'SELECT id, unidades_por_caja FROM productos WHERE id = ? AND activo = 1 FOR SHARE',
      [cuerpo.producto_id],
    );
    if (productos.length === 0) {
      return { estado: 422, cuerpo: { errores: { producto_id: MENSAJES.productoId } } }; // A4.6
    }

    // Regla propia (sección 6): se valida aquí; el CHECK de la base (3819) es la red de seguridad.
    const unidades = productos[0].unidades_por_caja;
    if (unidades !== null && cantidad.valor % unidades !== 0) {
      return { estado: 422, cuerpo: { errores: { cantidad: MENSAJES.porCaja(unidades) } } }; // RP2
    }

    // Repetido: lo decide el índice único (oportunidad_id, producto_id) → 1062 → 409 (A4.5).
    await conexion.execute(
      `INSERT INTO oportunidad_productos
              (oportunidad_id, producto_id, cantidad, precio_unitario, unidades_por_caja, created_at, updated_at)
       SELECT ?, p.id, ?, p.precio, p.unidades_por_caja, UTC_TIMESTAMP(), UTC_TIMESTAMP()
         FROM productos p
        WHERE p.id = ? AND p.activo = 1`,
      [id, cantidad.valor, cuerpo.producto_id],
    );

    return { estado: 201, cuerpo: await cargarDetalle(conexion, id, vendedorId) };
  });
});

/** 404 temprano de una línea: debe ser de ESTA oportunidad, y la oportunidad suya (A4.22). */
async function lineaEsPropia(oportunidadId, lineaId, vendedorId) {
  const [filas] = await pool.execute(
    `SELECT op.id FROM oportunidad_productos op
       JOIN oportunidades o ON o.id = op.oportunidad_id
      WHERE op.id = ? AND op.oportunidad_id = ? AND o.vendedor_id = ?`,
    [lineaId, oportunidadId, vendedorId],
  );

  return filas.length > 0;
}

// PATCH /oportunidades/:id/productos/:lineaId — { cantidad }, validada contra la COPIA de la línea.
router.patch('/:id/productos/:lineaId', exigirCuerpo, async (request, response) => {
  const { id, lineaId } = request.params;
  const vendedorId = request.usuario.id;
  if (!(await lineaEsPropia(id, lineaId, vendedorId))) {
    return response.status(404).json({ error: MENSAJES.lineaNoEncontrada });
  }

  const cantidad = validarCantidad(cuerpoDe(request).cantidad);
  if (cantidad.error) {
    return response.status(422).json({ errores: { cantidad: cantidad.error } }); // A4.8
  }

  return conErroresDeLaBase(response, async (conexion) => {
    const bloqueo = await bloquearAbierta(conexion, id, vendedorId);
    if (bloqueo.respuesta) {
      return bloqueo.respuesta;
    }

    const [lineas] = await conexion.execute(
      'SELECT id, unidades_por_caja FROM oportunidad_productos WHERE id = ? AND oportunidad_id = ? FOR UPDATE',
      [lineaId, id],
    );
    if (lineas.length === 0) {
      return { estado: 404, cuerpo: { error: MENSAJES.lineaNoEncontrada } };
    }

    // RP3/RP4: contra la copia guardada en la línea, no contra el catálogo de hoy.
    const unidades = lineas[0].unidades_por_caja;
    if (unidades !== null && cantidad.valor % unidades !== 0) {
      return { estado: 422, cuerpo: { errores: { cantidad: MENSAJES.porCaja(unidades) } } };
    }

    await conexion.execute(
      'UPDATE oportunidad_productos SET cantidad = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
      [cantidad.valor, lineaId],
    );

    return { estado: 200, cuerpo: await cargarDetalle(conexion, id, vendedorId) };
  });
});

// DELETE /oportunidades/:id/productos/:lineaId — 204 sin cuerpo.
router.delete('/:id/productos/:lineaId', async (request, response) => {
  const { id, lineaId } = request.params;
  const vendedorId = request.usuario.id;
  if (!(await lineaEsPropia(id, lineaId, vendedorId))) {
    return response.status(404).json({ error: MENSAJES.lineaNoEncontrada });
  }

  return conErroresDeLaBase(response, async (conexion) => {
    const bloqueo = await bloquearAbierta(conexion, id, vendedorId);
    if (bloqueo.respuesta) {
      return bloqueo.respuesta;
    }

    const [borrado] = await conexion.execute(
      'DELETE FROM oportunidad_productos WHERE id = ? AND oportunidad_id = ?',
      [lineaId, id],
    );
    if (borrado.affectedRows === 0) {
      return { estado: 404, cuerpo: { error: MENSAJES.lineaNoEncontrada } };
    }

    return { estado: 204 };
  });
});

export default router;
