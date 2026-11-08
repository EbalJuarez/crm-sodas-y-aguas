import { Router } from 'express';
import pool from '../db.js';

/** GET /productos — catálogo activo con la forma `Producto` (A4.18). Sin unidades_por_caja (3.2). */
const router = Router();

router.get('/', async (_request, response) => {
  const [filas] = await pool.execute(
    'SELECT id, nombre, precio FROM productos WHERE activo = 1 ORDER BY id',
  );

  return response.status(200).json(filas.map((fila) => ({
    id: fila.id,
    nombre: fila.nombre,
    precio: fila.precio,
  })));
});

export default router;
