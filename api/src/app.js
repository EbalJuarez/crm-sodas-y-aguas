import cors from 'cors';
import express from 'express';
import { autenticacion } from './middleware/autenticacion.js';
import { errorHandler, notFoundHandler } from './middleware/errores.js';
import authRouter from './routes/auth.js';

const app = express();
const corsOrigin = process.env.CORS_ORIGIN;

if (!corsOrigin) {
  throw new Error('La variable CORS_ORIGIN es obligatoria.');
}

app.use(cors({
  origin: (requestOrigin, callback) => {
    if (requestOrigin === undefined || requestOrigin === corsOrigin) {
      return callback(null, true);
    }

    return callback(null, false);
  },
}));
app.use(express.json());
// Única ruta pública: POST /api/auth/login.
app.use('/api/auth', authRouter);

// A2: todo lo demás bajo /api exige un token válido, incluso una ruta que no existe
// (sin token: 401; con token: sigue hasta el 404). Los routers protegidos se montan después.
app.use('/api', autenticacion);

app.use(notFoundHandler);
app.use(errorHandler);

export default app;
