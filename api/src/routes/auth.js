import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { Router } from 'express';
import pool from '../db.js';

const router = Router();
const credencialesInvalidas = 'El correo o la clave son incorrectos.';

router.post('/login', async (request, response) => {
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

  if (!user || !(await bcrypt.compare(body.clave, user.password))) {
    return response.status(401).json({ error: credencialesInvalidas });
  }

  if (user.rol !== 'vendedor' || !user.activo) {
    return response.status(403).json({
      error: 'La cuenta no tiene acceso a la aplicación de vendedores.',
    });
  }

  const secret = process.env.JWT_SECRET;
  if (!secret) {
    throw new Error('La variable JWT_SECRET es obligatoria.');
  }

  const token = jwt.sign(
    {},
    secret,
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
