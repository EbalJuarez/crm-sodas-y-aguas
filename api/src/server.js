import app from './app.js';

const port = Number.parseInt(process.env.PORT ?? '3000', 10);
const host = '127.0.0.1';

if (!Number.isInteger(port) || port < 1 || port > 65535) {
  throw new Error('La variable PORT debe ser un puerto válido.');
}

const server = app.listen(port, host, () => {
  console.log(`API escuchando en http://${host}:${port}.`);
});

server.on('error', (error) => {
  console.error(`No se pudo iniciar la API en http://${host}:${port}: ${error.message}`);
});
