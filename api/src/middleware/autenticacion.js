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

  // sub debe ser el id del usuario: entero positivo. Sin sub o con otro valor, 401 (nunca 500).
  const sub = typeof payload === 'object' && payload !== null ? payload.sub : undefined;
  if (!(typeof sub === 'string' || typeof sub === 'number') || !/^[1-9][0-9]{0,18}$/.test(String(sub))) {
    return response.status(401).json({ error: 'No autorizado.' });
  }

  const [rows] = await pool.execute(
    'SELECT id, name, email, rol, activo FROM users WHERE id = ? LIMIT 1',
    [String(sub)],
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
