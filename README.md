# CRM de ventas · Distribuidora de agua purificada y gaseosas

Proyecto final de Programación Web (UMES, 2026). Un CRM con tres roles y dos puertas sobre una sola
base MySQL:

| Pieza | Carpeta | Quién la usa | Tecnología |
|-------|---------|--------------|------------|
| Panel | `laravel/` | Administrador y supervisor | Laravel 13 + Breeze (Blade), PHP 8.4, Eloquent |
| API REST | `api/` | La SPA del vendedor | Node.js 24, Express 5, mysql2, jsonwebtoken, bcryptjs, cors |
| SPA | `frontend/` | Vendedor | HTML, Bootstrap 5.3, JavaScript con módulos |

Laravel es el único dueño del esquema (migraciones) y el único que crea cuentas.

## Documentación

- [Especificación](docs/especificacion.md)
- [Plan](docs/plan.md)
- [Verificación](docs/verificacion.md)
- [Colección de Postman](docs/api.postman_collection.json)
- El enunciado se consulta en los materiales del curso; no se incluye en este repositorio.

## Levantar el proyecto desde cero

_Pendiente: pasos exactos para MySQL, `laravel/`, `api/` y `frontend/`._

Cuentas de la semilla de verificación: contraseña `Ventas2026`.

## Endpoints

Base: `http://localhost:3000/api`. Todas las rutas exigen `Authorization: Bearer <token>`, salvo el inicio de sesión.

| Método | Ruta | Cuerpo o parámetros | Respuesta correcta |
|--------|------|---------------------|--------------------|
| POST | `/auth/login` | `{ correo, clave }` | 200 `{ token, usuario }` |
| GET | `/productos` | | 200, productos activos |
| GET | `/clientes` | `?q=` | 200, clientes de su cartera |
| POST | `/clientes` | `{ nombre, nit, telefono, correo }` | 201, el cliente |
| GET | `/clientes/disponibles` | | 200, `[{ id, nombre }]` |
| POST | `/clientes/:id/tomar` | | 200, el cliente |
| GET | `/clientes/:id` | | 200, el cliente |
| PATCH | `/clientes/:id` | campos que cambian | 200, el cliente |
| POST | `/clientes/:id/actividades` | `{ tipo, descripcion, vence_en }` | 201, la actividad |
| GET | `/actividades` | `?estado=pendiente\|completada` | 200, actividades |
| PATCH | `/actividades/:id` | `{ completada }` | 200, la actividad |
| GET | `/oportunidades` | `?etapa=` | 200, resumen |
| POST | `/oportunidades` | `{ cliente_id, titulo }` | 201, detalle |
| GET | `/oportunidades/:id` | | 200, detalle |
| PATCH | `/oportunidades/:id` | `{ titulo }` | 200, detalle |
| PATCH | `/oportunidades/:id/etapa` | `{ etapa, motivo_perdida }` | 200, detalle |
| POST | `/oportunidades/:id/productos` | `{ producto_id, cantidad }` | 201, detalle |
| PATCH | `/oportunidades/:id/productos/:lineaId` | `{ cantidad }` | 200, detalle |
| DELETE | `/oportunidades/:id/productos/:lineaId` | | 204 |
| GET | `/reportes/resumen` | `?mes=AAAA-MM` | 200, resumen del mes |
