import cors from 'cors';
import express from 'express';
import { errorHandler, notFoundHandler } from './middleware/errores.js';

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

app.use(notFoundHandler);
app.use(errorHandler);

export default app;
