import { Router } from 'express';
import pool from '../db.js';

const router = Router();
const ABIERTAS = ['prospecto', 'calificado', 'propuesta', 'negociacion'];
const MENSAJE_MES = 'El mes debe tener el formato AAAA-MM, por ejemplo 2026-10.';

function mesActualGuatemala() {
  const fecha = new Date(Date.now() - (6 * 60 * 60 * 1000));
  return `${fecha.getUTCFullYear()}-${String(fecha.getUTCMonth() + 1).padStart(2, '0')}`;
}

function esMesValido(mes) {
  return typeof mes === 'string' && /^20[0-9]{2}-(0[1-9]|1[0-2])$/.test(mes);
}

function limitesDelMes(mes) {
  const [anio, numero] = mes.split('-').map(Number);
  const inicio = new Date(Date.UTC(anio, numero - 1, 1, 6));
  const fin = new Date(Date.UTC(anio, numero, 1, 6));
  const sql = (fecha) => fecha.toISOString().slice(0, 19).replace('T', ' ');

  return [sql(inicio), sql(fin)];
}

const LINEAS = `
  SELECT oportunidad_id, SUM(cantidad * precio_unitario) AS total
    FROM oportunidad_productos
   GROUP BY oportunidad_id`;

router.get('/reportes/resumen', async (request, response) => {
  const { mes } = request.query;
  if (Object.keys(request.query).some((llave) => llave.startsWith('mes['))
    || (mes !== undefined && !esMesValido(mes))) {
    return response.status(400).json({ error: MENSAJE_MES });
  }

  const periodo = mes ?? mesActualGuatemala();
  const [inicio, fin] = limitesDelMes(periodo);
  const vendedorId = request.usuario.id;

  const [[ventas]] = await pool.execute(
    `SELECT COUNT(*) AS ventas, COALESCE(SUM(COALESCE(l.total, 0)), 0) AS total
       FROM oportunidades o
       LEFT JOIN (${LINEAS}) l ON l.oportunidad_id = o.id
      WHERE o.vendedor_id = ? AND o.etapa = 'ganada'
        AND o.fecha_cierre >= ? AND o.fecha_cierre < ?`,
    [vendedorId, inicio, fin],
  );

  const [[pronostico]] = await pool.execute(
    `SELECT ROUND(COALESCE(SUM(COALESCE(l.total, 0) * CASE o.etapa
        WHEN 'prospecto' THEN 10 WHEN 'calificado' THEN 25
        WHEN 'propuesta' THEN 50 WHEN 'negociacion' THEN 75 ELSE 0 END / 100), 0), 2) AS pronostico
       FROM oportunidades o
       LEFT JOIN (${LINEAS}) l ON l.oportunidad_id = o.id
      WHERE o.vendedor_id = ? AND o.etapa IN (?, ?, ?, ?)`,
    [vendedorId, ...ABIERTAS],
  );

  const [[cartera]] = await pool.execute(
    'SELECT COUNT(*) AS clientes FROM clientes WHERE vendedor_id = ?',
    [vendedorId],
  );

  return response.status(200).json({
    mes: periodo,
    ventas: Number(ventas.ventas),
    total: Number(ventas.total),
    pronostico: Number(pronostico.pronostico),
    cartera: { clientes: Number(cartera.clientes), maximo: 10 },
  });
});

export default router;
