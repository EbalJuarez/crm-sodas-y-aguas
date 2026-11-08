import app from './app.js';

const port = Number.parseInt(process.env.PORT ?? '3000', 10);

if (!Number.isInteger(port) || port < 1 || port > 65535) {
  throw new Error('La variable PORT debe ser un puerto válido.');
}

app.listen(port, () => {
  console.log(`API escuchando en el puerto ${port}.`);
});
