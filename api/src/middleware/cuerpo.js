/**
 * Exige un cuerpo JSON (spec 3.3: «falta el cuerpo» → 400).
 * Sin cuerpo (Content-Length 0 o ausente) o con un cuerpo que express.json no interpretó
 * (por ejemplo, Content-Type distinto de JSON): 400. Un objeto vacío `{}` sí es un cuerpo:
 * sigue a la validación por campo (422).
 */
export function exigirCuerpo(request, response, next) {
  const longitud = request.get('content-length');
  const tieneCuerpo = request.get('transfer-encoding') !== undefined
    || (longitud !== undefined && Number(longitud) > 0);

  if (!tieneCuerpo || request.body === undefined) {
    return response.status(400).json({
      error: 'Falta el cuerpo de la petición. Envíelo en formato JSON.',
    });
  }

  return next();
}
