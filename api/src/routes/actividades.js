import { Router } from 'express';
import pool from '../db.js';
import { exigirCuerpo } from '../middleware/cuerpo.js';

const router = Router();
const TIPOS = ['llamada', 'reunion', 'tarea'];
const COLUMNAS = `a.id, a.tipo, a.descripcion, a.vence_en, a.completada,
                  c.id AS cliente_id, c.nombre AS cliente_nombre`;
const MENSAJES = {
  idInvalido: 'El identificador no es válido.',
  cuerpoNoObjeto: 'El cuerpo debe ser un objeto JSON.',
  estado: 'El filtro estado debe ser pendiente o completada.',
  tipo: 'Elija llamada, reunión o tarea.',
  descripcion: 'Escriba la descripción.',
  descripcionLarga: 'La descripción puede tener hasta 500 caracteres.',
  venceEn: 'Escriba la fecha con su zona horaria.',
  completada: 'Indique verdadero o falso.',
  noEncontrada: 'No se encontró la actividad.',
};

function esObjeto(valor) {
  return typeof valor === 'object' && valor !== null && !Array.isArray(valor);
}

function longitud(texto) {
  return [...texto].length;
}

function fechaConZona(valor) {
  if (typeof valor !== 'string' || !/(?:Z|[+-][0-9]{2}:[0-9]{2})$/.test(valor)) {
    return null;
  }

  const fecha = new Date(valor);
  return Number.isNaN(fecha.getTime()) ? null : fecha;
}

function validarAlta(cuerpo) {
  const errores = {};
  const datos = {};

  if (typeof cuerpo.tipo !== 'string' || !TIPOS.includes(cuerpo.tipo)) {
    errores.tipo = MENSAJES.tipo;
  } else {
    datos.tipo = cuerpo.tipo;
  }

  if (typeof cuerpo.descripcion !== 'string' || cuerpo.descripcion.trim() === '') {
    errores.descripcion = MENSAJES.descripcion;
  } else if (longitud(cuerpo.descripcion.trim()) > 500) {
    errores.descripcion = MENSAJES.descripcionLarga;
  } else {
    datos.descripcion = cuerpo.descripcion.trim();
  }

  if (cuerpo.vence_en !== undefined && cuerpo.vence_en !== null) {
    const fecha = fechaConZona(cuerpo.vence_en);
    if (fecha === null) {
      errores.vence_en = MENSAJES.venceEn;
    } else {
      datos.vence_en = fecha;
    }
  } else {
    datos.vence_en = null;
  }

  return Object.keys(errores).length > 0 ? { errores } : { datos };
}

function aActividad(fila) {
  const venceEn = fila.vence_en ? new Date(fila.vence_en) : null;
  const completada = Boolean(fila.completada);

  return {
    id: fila.id,
    cliente: { id: fila.cliente_id, nombre: fila.cliente_nombre },
    tipo: fila.tipo,
    descripcion: fila.descripcion,
    vence_en: venceEn ? venceEn.toISOString() : null,
    completada,
    vencida: !completada && venceEn !== null && venceEn.getTime() < Date.now(),
  };
}

router.param('id', (_request, response, next, valor) => {
  if (!/^[0-9]{1,20}$/.test(valor)) {
    return response.status(400).json({ error: MENSAJES.idInvalido });
  }

  return next();
});

// GET /actividades: la autorización sale de la cartera actual del cliente, nunca de a.vendedor_id.
router.get('/actividades', async (request, response) => {
  const { estado } = request.query;
  const estadoConForma = Object.keys(request.query).some((llave) => llave.startsWith('estado['));
  if (estadoConForma || (estado !== undefined && !['pendiente', 'completada'].includes(estado))) {
    return response.status(400).json({ error: MENSAJES.estado });
  }

  const parametros = [request.usuario.id];
  const filtro = estado === undefined ? '' : ' AND a.completada = ?';
  if (estado !== undefined) {
    parametros.push(estado === 'completada' ? 1 : 0);
  }

  const [filas] = await pool.execute(
    `SELECT ${COLUMNAS}
       FROM actividades a
       JOIN clientes c ON c.id = a.cliente_id
      WHERE c.vendedor_id = ?${filtro}
      ORDER BY a.id`,
    parametros,
  );

  return response.status(200).json(filas.map(aActividad));
});

// POST /clientes/:id/actividades: el cliente debe estar en la cartera actual.
router.post('/clientes/:id/actividades', exigirCuerpo, async (request, response) => {
  const cuerpo = request.body;
  if (!esObjeto(cuerpo)) {
    return response.status(400).json({ error: MENSAJES.cuerpoNoObjeto });
  }

  const [clientes] = await pool.execute(
    'SELECT id FROM clientes WHERE id = ? AND vendedor_id = ?',
    [request.params.id, request.usuario.id],
  );
  if (clientes.length === 0) {
    return response.status(404).json({ error: MENSAJES.noEncontrada });
  }

  const resultado = validarAlta(cuerpo);
  if (resultado.errores) {
    return response.status(422).json({ errores: resultado.errores });
  }

  const { tipo, descripcion, vence_en: venceEn } = resultado.datos;
  const [insercion] = await pool.execute(
    `INSERT INTO actividades
       (cliente_id, vendedor_id, tipo, descripcion, vence_en, completada, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())`,
    [request.params.id, request.usuario.id, tipo, descripcion, venceEn],
  );

  const [filas] = await pool.execute(
    `SELECT ${COLUMNAS}
       FROM actividades a JOIN clientes c ON c.id = a.cliente_id
      WHERE a.id = ? AND c.vendedor_id = ?`,
    [insercion.insertId, request.usuario.id],
  );

  return response.status(201).json(aActividad(filas[0]));
});

// PATCH /actividades/:id: una reasignación entre la lectura y el UPDATE vuelve a ser 404.
router.patch('/actividades/:id', exigirCuerpo, async (request, response) => {
  const cuerpo = request.body;
  if (!esObjeto(cuerpo)) {
    return response.status(400).json({ error: MENSAJES.cuerpoNoObjeto });
  }
  if (!Object.hasOwn(cuerpo, 'completada') || typeof cuerpo.completada !== 'boolean') {
    return response.status(422).json({ errores: { completada: MENSAJES.completada } });
  }

  const [actualizacion] = await pool.execute(
    `UPDATE actividades a
        JOIN clientes c ON c.id = a.cliente_id
       SET a.completada = ?, a.updated_at = UTC_TIMESTAMP()
     WHERE a.id = ? AND c.vendedor_id = ?`,
    [cuerpo.completada ? 1 : 0, request.params.id, request.usuario.id],
  );
  if (actualizacion.affectedRows === 0) {
    return response.status(404).json({ error: MENSAJES.noEncontrada });
  }

  const [filas] = await pool.execute(
    `SELECT ${COLUMNAS}
       FROM actividades a JOIN clientes c ON c.id = a.cliente_id
      WHERE a.id = ? AND c.vendedor_id = ?`,
    [request.params.id, request.usuario.id],
  );

  return response.status(200).json(aActividad(filas[0]));
});

export default router;
