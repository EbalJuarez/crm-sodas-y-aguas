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

Requisitos: PHP 8.4 con Composer, Node.js 24, MySQL 8.4 y un navegador moderno. Los comandos siguientes
usan PowerShell y se ejecutan desde la raíz del repositorio.

1. Cree las bases vacías `crm_agua` y `crm_agua_test` en MySQL. El usuario debe tener permisos para crear,
   modificar y eliminar tablas en ambas bases.
2. Configure Laravel:

   ```powershell
   Copy-Item laravel/.env.example laravel/.env
   composer install --working-dir=laravel
   php laravel/artisan key:generate
   ```

   Edite `laravel/.env` y complete `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`.
   Mantenga `APP_TIMEZONE=UTC`, `APP_LOCALE=es` y `APP_FALLBACK_LOCALE=es`.

3. Configure la API:

   ```powershell
   Copy-Item api/.env.example api/.env
   npm install --prefix api
   ```

   Complete en `api/.env` los mismos datos de MySQL y un `JWT_SECRET` aleatorio de al menos 32 caracteres.
   Mantenga `CORS_ORIGIN=http://127.0.0.1:5500`; no use `*`.

4. Migre y cargue los datos. `--seed` carga primero la verificación oficial y luego datos sectoriales de
   Distribuidora Aguaviva. Para las cifras del enunciado use solo `VerificacionSeeder`.

   ```powershell
   php laravel/artisan migrate:fresh --seed
   # Solo verificacion:
   php laravel/artisan migrate:fresh --seeder=VerificacionSeeder
   ```

5. Abra tres terminales desde la raíz:

   ```powershell
   php laravel/artisan serve --host=127.0.0.1 --port=8000
   npm start --prefix api
   php -S 127.0.0.1:5500 -t frontend
   ```

   Panel: `http://127.0.0.1:8000`. SPA: `http://127.0.0.1:5500`. API: `http://127.0.0.1:3000/api`.

La contraseña pública de las cuentas de las semillas es `Ventas2026`. Con la semilla de verificación,
Valeria (`valeria@crm.test`) es la cuenta de vendedor para la SPA; Alicia (`admin@crm.test`) y Sergio
(`sergio@crm.test`) entran al panel. No existe registro público.

## Pruebas

```powershell
php laravel/artisan test
npm test --prefix api
```

Las pruebas destructivas deben apuntar a `crm_agua_test`, nunca a `crm_agua`.

## Endpoints

Base: `http://localhost:3000/api`. Todas las rutas exigen `Authorization: Bearer <token>`, salvo el inicio de sesión.

| Método | Ruta | Cuerpo o parámetros | Éxito | Errores principales |
|--------|------|---------------------|--------|--------------------|
| POST | `/auth/login` | `{ correo, clave }` | 200 `{ token, usuario }` | 400 JSON roto, 401 credenciales, 403 rol no vendedor, 422 campos |
| GET | `/productos` | — | 200 productos activos | 401 |
| GET | `/clientes` | `?q=` | 200 cartera | 400 query inválida, 401 |
| POST | `/clientes` | `{ nombre, nit, telefono, correo }` | 201 cliente | 401, 409 duplicado/cartera, 422 campos |
| GET | `/clientes/disponibles` | — | 200 `[{ id, nombre }]` | 401 |
| POST | `/clientes/:id/tomar` | — | 200 cliente | 401, 404 id ajeno/inexistente, 409 cartera |
| GET | `/clientes/:id` | — | 200 cliente | 401, 404 |
| PATCH | `/clientes/:id` | campos que cambian | 200 cliente | 401, 404, 409, 422 |
| POST | `/clientes/:id/actividades` | `{ tipo, descripcion, vence_en }` | 201 actividad | 401, 404, 422 |
| GET | `/actividades` | `?estado=pendiente\|completada` | 200 actividades | 400 filtro, 401 |
| PATCH | `/actividades/:id` | `{ completada }` | 200 actividad | 401, 404, 422 |
| GET | `/oportunidades` | `?etapa=` | 200 resumen | 400 filtro, 401 |
| POST | `/oportunidades` | `{ cliente_id, titulo }` | 201 detalle | 401, 422 cartera/campos |
| GET | `/oportunidades/:id` | — | 200 detalle | 400 id, 401, 404 |
| PATCH | `/oportunidades/:id` | `{ titulo }` | 200 detalle | 401, 404, 422 |
| PATCH | `/oportunidades/:id/etapa` | `{ etapa, motivo_perdida }` | 200 detalle | 401, 404, 409 transición, 422 |
| POST | `/oportunidades/:id/productos` | `{ producto_id, cantidad }` | 201 detalle | 401, 404, 409 duplicado, 422 cantidad |
| PATCH | `/oportunidades/:id/productos/:lineaId` | `{ cantidad }` | 200 detalle | 401, 404, 409 etapa, 422 cantidad |
| DELETE | `/oportunidades/:id/productos/:lineaId` | — | 204 sin cuerpo | 401, 404, 409 |
| GET | `/reportes/resumen` | `?mes=AAAA-MM` | 200 resumen | 400 mes, 401 |

Todos los endpoints salvo `/auth/login` requieren `Authorization: Bearer <token>` y responden JSON.
Los ids ajenos se ocultan con 404. Para comprobar la regla propia, use un producto con
`unidades_por_caja` y cantidades múltiplos de ese valor; el catálogo copia ese valor en cada línea.
La colección `docs/api.postman_collection.json` contiene una petición por endpoint, variables y pruebas.
