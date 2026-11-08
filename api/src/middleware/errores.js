export function notFoundHandler(_request, response) {
  response.status(404).json({
    error: 'La ruta solicitada no existe.',
  });
}

export function errorHandler(error, _request, response, _next) {
  if (error instanceof SyntaxError && error.status === 400 && error.type === 'entity.parse.failed') {
    return response.status(400).json({
      error: 'El cuerpo JSON no tiene un formato válido.',
    });
  }

  console.error(error);

  return response.status(500).json({
    error: 'Ocurrió un error interno del servidor.',
  });
}
