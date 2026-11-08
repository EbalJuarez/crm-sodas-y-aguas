import jwt from 'jsonwebtoken';
import pool from '../db.js';

export async function autenticacion(request, response, next) {
  const authorization = request.get('authorization');
  const match = authorization?.match(/^Bearer ([^ ]+)$/);
  const secret = process.env.JWT_SECRET;

  if (!match || !secret) {
    return response.status(401).json({ error: 'No autorizado.' });
  }

  let payload;
  try {
    payload = jwt.verify(match[1], secret, { algorithms: ['HS256'] });
  } catch {
    return response.status(401).json({ error: 'No autorizado.' });
  }

  const [rows] = await pool.execute(
    'SELECT id, name, email, rol, activo FROM users WHERE id = ? LIMIT 1',
    [payload.sub],
  );
  const user = rows[0];

  if (!user || user.rol !== 'vendedor' || !user.activo) {
    return response.status(401).json({ error: 'No autorizado.' });
  }

  request.usuario = {
    id: user.id,
    nombre: user.name,
    correo: user.email,
  };

  return next();
}
