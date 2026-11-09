import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { Router } from 'express';
import pool from '../db.js';
import { exigirCuerpo } from '../middleware/cuerpo.js';

const router = Router();
const credencialesInvalidas = 'El correo o la clave son incorrectos.';

// Hash bcrypt fijo de un texto aleatorio que nadie conoce (costo 12, como los de Laravel).
// Se compara contra él cuando el correo no existe, para que esa respuesta tarde lo mismo
// que una clave incorrecta y no se puedan adivinar correos por el tiempo. Es una constante:
// la API no genera hashes (S1), solo los compara.
const HASH_FICTICIO = '$2y$12$5CXnlHKXLNARCq23abG8G.qBs4eEhYlrU1TlGf6ccskasRiqo3oQ2';

router.post('/login', exigirCuerpo, async (request, response) => {
  const body = request.body ?? {};
  const errores = {};

  if (typeof body.correo !== 'string' || body.correo.trim() === '') {
    errores.correo = 'Escriba su correo.';
  }

  if (typeof body.clave !== 'string' || body.clave === '') {
    errores.clave = 'Escriba su clave.';
  }

  if (Object.keys(errores).length > 0) {
    return response.status(422).json({ errores });
  }

  const correo = body.correo.trim();
  const [rows] = await pool.execute(
    'SELECT id, name, email, password, rol, activo FROM users WHERE email = ? LIMIT 1',
    [correo],
  );
  const user = rows[0];

  // Siempre se ejecuta una comparación bcrypt, exista o no el correo.
  const claveCorrecta = await bcrypt.compare(body.clave, user ? user.password : HASH_FICTICIO);

  if (!user || !claveCorrecta) {
    return response.status(401).json({ error: credencialesInvalidas });
  }

  if (user.rol !== 'vendedor' || !user.activo) {
    return response.status(403).json({
      error: 'La cuenta no tiene acceso a la aplicación de vendedores.',
    });
  }

  // JWT_SECRET ya se validó al arrancar (app.js).
  const token = jwt.sign(
    {},
    process.env.JWT_SECRET,
    {
      algorithm: 'HS256',
      subject: String(user.id),
      expiresIn: process.env.JWT_EXPIRES_IN ?? '8h',
    },
  );

  return response.status(200).json({
    token,
    usuario: {
      id: user.id,
      nombre: user.name,
      correo: user.email,
    },
  });
});

export default router;
