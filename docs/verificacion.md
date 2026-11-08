# Verificación

## Matriz de criterios y evidencia (P3)

| Criterio | Evidencia | Resultado |
|----------|-----------|-----------|
| A1.1 | API real: Valeria responde 200 con `{ token, usuario: { id: 4, nombre, correo } }`, sin hash, rol ni `supervisor_id` («Bloque 2», A1) | ✔ |
| A1.2 | API real: Alicia, Sergio e Iván responden 403 `{ error }`, sin token | ✔ |
| A1.3 | API real: correo inexistente y clave incorrecta dan 401 con cuerpos idénticos (`cmp` sin diferencias), con tiempos parecidos | ✔ |
| A1.4 | API real: sin `correo` o sin `clave` da 422 con un mensaje por campo | ✔ |
| A1.5 | API real: JSON mal formado da 400 | ✔ |
| A1.6 | Token decodificado: `alg` HS256, `sub` `"4"`, `exp − iat` = 28800 s (`JWT_EXPIRES_IN=8h`) | ✔ |
| A1.7 | API real: sin cuerpo da 400; `{}` da 422 por campo | ✔ |
| A2.1 | API real sin token: `/api/clientes`, `/api/no-existe` y `GET /api/auth/login` dan 401 | ✔ |
| A2.2 | API real: token alterado, vencido, `none`, otro secreto, HS512, sin `sub` y `sub` no numérico dan 401 (ningún 500 en consola) | ✔ |
| A2.3 | API real, mismo token válido: Valeria desactivada da 401; con rol `supervisor`, 401; restaurada, 404 (ruta inexistente). Cambios hechos con SQL directo sobre `users`, no desde el panel | ✔ |
| S6 | API real: la preflight desde `http://127.0.0.1:5500` recibe `Access-Control-Allow-Origin`; desde `http://localhost:5500` y `http://evil.test`, no | ✔ |
| L3.1 | `ProductosTest`: el administrador puede crear y editar un producto | ✔ |
| L3.2 | Panel: precio `0`, `abc`, `10.999` y `100000000` vuelven con **un** mensaje junto al precio; `ProductosTest` | ✔ |
| L3.3 | `ProductosTest`: rechaza nombres duplicados | ✔ |
| L3.4 | Panel: `DELETE /productos/5` da 405; la lista no ofrece borrar; el supervisor recibe 403 en `/productos` | ✔ |
| L3.5 | Panel + SQL: Plan avanzado pasa a Q9,999.99 y caja 12; las 3 líneas no cambian; Valeria, octubre: 2 ventas, 9250.00 | ✔ |
| RP5 | SQL directo: cantidad 30 con caja 24 da error 3819 `oportunidad_productos_caja_completa`; con 48, se acepta; `productos.unidades_por_caja` 0 y 1000 dan 3819 | ✔ |
| RP7 | Panel: `unidades_por_caja` 0, 1000 y 2.5 vuelven con mensaje junto al campo | ✔ |
| L4.1 | Panel: Alicia ve «Mostrando 1 a 20 de 21 resultados»; `ClientesSemillaTest` L4.1 y L4.2 (búsqueda y filtros) | ✔ |
| L4.2 | `ClientesSemillaTest`: L4.1 y L4.2 respetan la visibilidad y los filtros | ✔ |
| L4.3–L4.6 | `ClientesSemillaTest`: L4.3–L4.6 validan identidad; `ClientesTest`: casos de `docs/casos-normalizacion.json` | ✔ |
| L4.7 | `ClientesSemillaTest`: rechaza la cartera número 11; `ClientesTest`: dos asignaciones no superan el máximo | ✔ |
| L4.8 | `ClientesSemillaTest`: reasigna las abiertas, conserva las cerradas y el historial | ✔ |
| L4.9 | `ClientesSemillaTest`: rechaza liberar con oportunidades abiertas | ✔ |
| L4.10, L4.11 | Panel: Alicia no puede eliminar Comercial El Quetzal («No puede eliminar un cliente que tiene oportunidades.»); `ClientesSemillaTest` L4.10 y L4.11 | ✔ |
| L4.12 | `ClientesSemillaTest`: no permite asignar a un vendedor desactivado | ✔ |
| L4.13 | `ClientesSemillaTest`: libera a María Coyoy | ✔ |
| L4.14 | `ClientesSemillaTest`: editar con valores repetidos falla; sin cambios no choca | ✔ |
| L4.15–L4.17 | `ClientesSemillaTest`: vendedores desactivados y reactivación | ✔ |
| L4.18 | Panel: Sergio no ve «Eliminar» y `DELETE /clientes/20` da 403; `ClientesTest` | ✔ |
| S5 (panel) | Panel: Sergio sobre Abarrotes San Juan (de Carlos) recibe 404 en edit, PUT inválido, PUT válido, asignar y liberar, igual que con el id 9999; `ClientesTest` | ✔ |
| L7.1 (clientes y catálogo) | Panel: nombre, NIT o teléfono vacíos al editar vuelven con un mensaje en español junto al campo y no se guardan; `ClientesTest` | ✔ |
| F1.1 | SPA en Edge sin ventana: Valeria entra y queda `crm_agua_token` en `localStorage` | ✔ |
| F1.2 | SPA: Sergio ve «La cuenta no tiene acceso a la aplicación de vendedores.» (texto de la API) | ✔ |
| F1.3 | SPA: al cerrar sesión, `localStorage` queda vacío | ✔ |
| F1.4 | SPA: con un token alterado en plena sesión, `GET /api/clientes` da 401 y la SPA vuelve al inicio de sesión con «Su sesión ya no es válida. Inicie sesión nuevamente.» (la petición se lanzó con el `api.js` de la SPA desde la consola: todavía no hay vistas que llamen rutas protegidas) | ✔ |
| F1.5 | SPA: la única clave en `localStorage` es `crm_agua_token` | ✔ |
| F6.3 (adelanto) | SPA con la API apagada: «No se pudo conectar con la API. Compruebe que esté en marcha.» | ✔ |
| F6.5 (solo login e inicio) | SPA a 375 px: `scrollWidth` = 375 en todas las pantallas de F1 | ✔ |

## Pruebas de seguridad (S1–S8), incluidas las de peticiones simultáneas

## Cifras de control obtenidas

## L1 y L2

Evidencia obtenida el 2026-11-08 sobre el commit `62dbb32`, con MySQL 8.0.40 (falta repetirla en
MySQL 8.4 antes de la entrega). Base cargada con
`php artisan migrate:fresh --seeder=VerificacionSeeder` (sin errores).

### L1 — Esquema y semilla

**Conteos de la semilla (L1.4):** 7 cuentas, 5 productos, 21 clientes, 11 oportunidades,
43 registros de historial, 4 actividades. Coincide con la sección 11.

**Los `CHECK` rechazan (L1.3, RP5).** Cada prueba corrió en una transacción revertida; la base quedó
con 43 registros de historial y 11 oportunidades.

| Prueba | Esperado | Obtenido |
|--------|----------|----------|
| Teléfono de 7 dígitos (`5551234`) | rechaza | MySQL 3819, `clientes_telefono_formato` |
| Control: teléfono de 8 dígitos válido | acepta | 1 fila |
| Cantidad 30 con `unidades_por_caja` 24 | rechaza | MySQL 3819, `oportunidad_productos_caja_completa` |
| Control: cantidad 48 con `unidades_por_caja` 24 | acepta | 1 fila |
| `unidades_por_caja` 0 | rechaza | MySQL 3819, `oportunidad_productos_caja_completa` |
| *Ampliación a bodega* a `ganada` sin fecha de cierre | rechaza | MySQL 3819, `oportunidades_cierre_sii_cerrada` |
| Control: `ganada` con fecha de cierre | acepta | 1 fila |
| `perdida` sin motivo | rechaza | MySQL 3819, `oportunidades_motivo_sii_perdida` |
| Precio 0 en el catálogo | rechaza | MySQL 3819, `productos_precio_positivo` |
| NIT con `k` minúscula | rechaza | MySQL 3819, `clientes_nit_compacto` |
| NIT repetido `4521817K` | rechaza | MySQL 1062, `clientes_nit_unique` |
| Vendedor sin supervisor | rechaza | MySQL 3819, `users_supervisor_segun_rol` |

**Cifras de control.** Todas las consultas usan el total con el precio **guardado en la línea** y el
mes en **hora de Guatemala** (UTC-6). En cada consulta, `T` y `M` se sustituyen por:

```sql
-- T: total de la oportunidad o
(SELECT COALESCE(SUM(op.cantidad * op.precio_unitario), 0) FROM oportunidad_productos op WHERE op.oportunidad_id = o.id)
-- M: mes de cierre en hora de Guatemala
DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m')
-- P: probabilidad de una etapa abierta
CASE o.etapa WHEN 'prospecto' THEN 10 WHEN 'calificado' THEN 25 WHEN 'propuesta' THEN 50 WHEN 'negociacion' THEN 75 END
```

Ventas y total de un vendedor en un mes:

```sql
SELECT COUNT(*) ventas, COALESCE(SUM(T), 0) total
  FROM oportunidades o JOIN users u ON u.id = o.vendedor_id
 WHERE u.email = ? AND o.etapa = 'ganada' AND M = ?
```

Pronóstico de un vendedor (sin filtro de mes):

```sql
SELECT ROUND(SUM(T * P / 100), 2) pronostico
  FROM oportunidades o JOIN users u ON u.id = o.vendedor_id
 WHERE u.email = ? AND o.etapa IN ('prospecto','calificado','propuesta','negociacion')
```

Equipo de un supervisor (ventas de octubre y pronóstico):

```sql
SELECT COUNT(*) ventas, COALESCE(SUM(T), 0) total
  FROM oportunidades o JOIN users v ON v.id = o.vendedor_id JOIN users s ON s.id = v.supervisor_id
 WHERE s.email = ? AND o.etapa = 'ganada' AND M = '2026-10';

SELECT ROUND(SUM(T * P / 100), 2) pronostico
  FROM oportunidades o JOIN users v ON v.id = o.vendedor_id JOIN users s ON s.id = v.supervisor_id
 WHERE s.email = ? AND o.etapa IN ('prospecto','calificado','propuesta','negociacion');
```

Carteras:

```sql
SELECT u.name, COUNT(c.id) clientes
  FROM users u LEFT JOIN clientes c ON c.vendedor_id = u.id
 WHERE u.rol = 'vendedor' GROUP BY u.id, u.name ORDER BY u.id;
```

Toda la empresa (pronóstico) y por producto:

```sql
SELECT ROUND(SUM(T * P / 100), 2) FROM oportunidades o
 WHERE o.etapa IN ('prospecto','calificado','propuesta','negociacion');

SELECT p.nombre, SUM(op.cantidad) unidades, SUM(op.cantidad * op.precio_unitario) total
  FROM oportunidades o JOIN oportunidad_productos op ON op.oportunidad_id = o.id
  JOIN productos p ON p.id = op.producto_id
 WHERE o.etapa = 'ganada' AND M = '2026-10' GROUP BY p.id, p.nombre ORDER BY p.id;
```

| Quién | Mes | Ventas | Total | Pronóstico | Cartera | Enunciado | ¿Coincide? |
|-------|-----|--------|-------|------------|---------|-----------|------------|
| Valeria López | oct 2026 | 2 | 9250.00 | 2512.88 | 3 | 2 · Q9,250.00 · Q2,512.88 · 3 de 10 | ✅ |
| Valeria López | nov 2026 | 1 | 3600.00 | 2512.88 | 3 | 1 · Q3,600.00 · Q2,512.88 · 3 de 10 | ✅ |
| Diego Castillo | oct 2026 | 1 | 6000.00 | 112.50 | 2 | 1 · Q6,000.00 · Q112.50 · 2 de 10 | ✅ |
| Carlos Ajú | oct 2026 | 1 | 7200.00 | 4125.75 | 9 | 1 · Q7,200.00 · Q4,125.75 · 9 de 10 | ✅ |
| Equipo de Sergio Ruiz | oct 2026 | 3 | 15250.00 | 2625.38 | — | 3 · Q15,250.00 · Q2,625.38 | ✅ |
| Equipo de Sandra Pérez | oct 2026 | 1 | 7200.00 | 4125.75 | — | 1 · Q7,200.00 · Q4,125.75 | ✅ |
| Toda la empresa | oct 2026 | 4 | 22450.00 | 6751.13 | — | 4 · Q22,450.00 · Q6,751.13 | ✅ |

La cartera de Iván Toj (desactivado) es 1 (Hotel Los Pinos); el enunciado no da cifra para él.

| Producto · octubre 2026 | Unidades | Total | Enunciado | ¿Coincide? |
|-------------------------|----------|-------|-----------|------------|
| Plan básico | 5 | 6000.00 | 5 · Q6,000.00 | ✅ |
| Plan avanzado | 2 | 5200.00 | 2 · Q5,200.00 | ✅ |
| Instalación | 1 | 450.00 | 1 · Q450.00 | ✅ |
| Soporte anual | 3 | 10800.00 | 3 · Q10,800.00 | ✅ |

`php artisan migrate:fresh --seed` carga `VerificacionSeeder` y `SectorSeeder` (vacío) y no crea
ningún usuario de prueba: 7 usuarios, todos de la semilla (L1.5).

### L2 — Cuentas y roles

`php artisan test` sobre la base `crm_agua_test` (MySQL): **OK, 50 pruebas, 145 aserciones**.

| Criterio | Prueba | Resultado |
|----------|--------|-----------|
| L2.1 | `RegistrationTest`: la página de registro no existe (404); no se puede enviar un registro (404); no existe la ruta `register`; el login no enlaza a un registro | ✔ |
| L2.2 | `CuentasTest`: una cuenta nueva creada por el administrador entra de inmediato (hash `$2y$`, sin verificar correo) | ✔ |
| L2.2 | `CuentasTest`: un vendedor se crea con su supervisor | ✔ |
| L2.3 | `CuentasTest`: un vendedor sin supervisor o con uno inactivo se rechaza | ✔ |
| L2.4 | `CuentasTest`: un supervisor enviado a un administrador o supervisor queda vacío | ✔ |
| L2.5 | `CuentasTest`: las cuentas no se borran (`DELETE /usuarios/{id}` → 405) | ✔ |
| L2.5 | `ProfileTest`: un usuario no puede borrar su cuenta desde el perfil; el perfil no ofrece borrarla | ✔ |
| L2.6 | `CuentasTest`: el administrador no puede desactivarse; no puede quitarse el rol | ✔ |
| L2.7 | `AccesoPanelTest`: el vendedor no entra y se le envía a la SPA; un vendedor con sesión abierta es expulsado | ✔ |
| L2.8 | `AccesoPanelTest`: una cuenta desactivada no entra | ✔ |
| L2.9 | `AccesoPanelTest`: una cuenta desactivada pierde la sesión en la siguiente petición | ✔ |
| L2.10 | `AccesoPanelTest`: el administrador entra; el supervisor entra; una clave incorrecta no entra | ✔ |
| L2.11 | `AuthenticationTest`: users can logout | ✔ |
| L2.12 | `CuentasTest`: no se cambia el rol de un supervisor con vendedores (mensaje de D6) | ✔ |
| L2.13 | `CuentasTest`: no se cambia el rol de un vendedor con clientes (mensaje de D6) | ✔ |
| L2.14 | `CuentasTest`: sí se puede desactivar a un supervisor con equipo; su vendedor conserva el supervisor y al editarlo se exige uno activo | ✔ |
| L7.1 (parcial) | `CuentasTest`: los mensajes y los datos escritos vuelven al formulario | ✔ |
| S7 (panel, parcial) | `CuentasTest`: la lista escapa los nombres (`<img src=x onerror=alert(1)>`) | ✔ |
| — | `AccesoPanelTest`: el panel no exige verificar el correo; `CuentasTest`: editar sin contraseña no cambia el hash; las pantallas de alta y edición se muestran; un supervisor no administra cuentas (403) | ✔ |

Las 20 pruebas restantes son las de Breeze (autenticación, verificación de correo, confirmación,
recuperación y cambio de contraseña, perfil) y los dos ejemplos; todas pasan.

## Bloque 2 (A1, A2, L3, L4, F1)

Evidencia obtenida el 2026-11-08 sobre el commit `563e6ce` (código del bloque 2 con las correcciones
`bfcdee2`…`ae9afaa`), con MySQL 8.0.40 (falta repetirla en MySQL 8.4 antes de la entrega). Base
recargada con `php artisan migrate:fresh --seeder=VerificacionSeeder` antes de las pruebas del panel.

Cómo se obtuvo:

- **API real**, levantada desde `api/` con `node src/server.js`. Las variables (`DB_*`, `JWT_SECRET`
  aleatorio de 64 caracteres, `JWT_EXPIRES_IN=8h`, `CORS_ORIGIN=http://127.0.0.1:5500`) iban en el
  entorno del proceso; no se creó `api/.env`. Peticiones con `curl.exe` y la contraseña pública de las
  semillas. El token se recorta aquí.
- **Panel** con `php artisan serve` y `APP_LOCALE=es`, recorrido con peticiones HTTP con sesión y CSRF.
- **SPA** servida en `http://127.0.0.1:5500` y manejada con Edge sin ventana (CDP) a 375 px.
- **Pruebas:** `vendor/bin/phpunit --testdox`. En el entorno de la auditoría, `php artisan test` solo
  imprime un resumen JSON (`{"tests":88,"passed":88,"assertions":460}`), así que se pega la salida
  de PHPUnit.

### Conteos de la semilla

```
> SELECT (SELECT COUNT(*) FROM users) cuentas,(SELECT COUNT(*) FROM productos) productos,(SELECT COUNT(*) FROM clientes) clientes,(SELECT COUNT(*) FROM oportunidades) oportunidades,(SELECT COUNT(*) FROM oportunidad_productos) lineas,(SELECT COUNT(*) FROM historial_etapas) historial,(SELECT COUNT(*) FROM actividades) actividades
  {"cuentas":7,"productos":5,"clientes":21,"oportunidades":11,"lineas":13,"historial":43,"actividades":4}
> SELECT @@version version
  {"version":"8.0.40"}
```

### A1 — Inicio de sesión (API real)

```
API escuchando en el puerto 3000.
Valeria, clave correcta                     HTTP 200 {"token":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.…","usuario":{"id":4,"nombre":"Valeria López","correo":"valeria@crm.test"}}
Alicia (admin)                              HTTP 403 {"error":"La cuenta no tiene acceso a la aplicación de vendedores."}
Sergio (supervisor)                         HTTP 403 {"error":"La cuenta no tiene acceso a la aplicación de vendedores."}
Iván (desactivado)                         HTTP 403 {"error":"La cuenta no tiene acceso a la aplicación de vendedores."}
correo inexistente                          HTTP 401 {"error":"El correo o la clave son incorrectos."}
Valeria, clave incorrecta                   HTTP 401 {"error":"El correo o la clave son incorrectos."}
  -> los dos cuerpos 401 son idénticos byte a byte (cmp sin diferencias)
sin cuerpo (sin Content-Type)               HTTP 400 {"error":"Falta el cuerpo de la petición. Envíelo en formato JSON."}
sin cuerpo (Content-Type JSON, vacío)      HTTP 400 {"error":"Falta el cuerpo de la petición. Envíelo en formato JSON."}
cuerpo de texto plano                       HTTP 400 {"error":"Falta el cuerpo de la petición. Envíelo en formato JSON."}
JSON mal formado                            HTTP 400 {"error":"El cuerpo JSON no tiene un formato válido."}
{} (objeto vacío)                          HTTP 422 {"errores":{"correo":"Escriba su correo.","clave":"Escriba su clave."}}
sin correo                                  HTTP 422 {"errores":{"correo":"Escriba su correo."}}
sin clave                                   HTTP 422 {"errores":{"clave":"Escriba su clave."}}
--- tiempos (5 intentos cada uno, segundos)
nadie@crm.test    0.219961 0.216530 0.216493 0.222032 0.224744 
valeria@crm.test  0.218485 0.217173 0.218852 0.224749 0.222866 
```

Token de Valeria decodificado (sin verificar firma):

```
{"alg":"HS256","typ":"JWT"} {"iat":1794174689,"exp":1794203489,"sub":"4"} exp-iat=28800s
```

### A2 — Middleware (API real)

Los tokens inválidos se firmaron aparte con `jsonwebtoken`. Desactivar a Valeria y cambiarle el rol se
hizo con SQL directo sobre `users`, y se restauró al final.

```
API escuchando en el puerto 3000.
=== Sin token
GET /api/clientes (sin token)                 {"error":"No autorizado."}  -> HTTP 401
GET /api/no-existe (sin token)                {"error":"No autorizado."}  -> HTTP 401
GET /api/auth/login (sin token, no es POST)   {"error":"No autorizado."}  -> HTTP 401
GET /no-existe (fuera de /api)                {"error":"La ruta solicitada no existe."}  -> HTTP 404
=== Con token valido de Valeria
GET /api/no-existe (token valido)             {"error":"La ruta solicitada no existe."}  -> HTTP 404
=== Tokens invalidos (ruta /api/no-existe)
token alterado                                {"error":"No autorizado."}  -> HTTP 401
token vencido                                 {"error":"No autorizado."}  -> HTTP 401
token none                                    {"error":"No autorizado."}  -> HTTP 401
token otroSecreto                             {"error":"No autorizado."}  -> HTTP 401
token hs512                                   {"error":"No autorizado."}  -> HTTP 401
token sinSub                                  {"error":"No autorizado."}  -> HTTP 401
token subTexto                                {"error":"No autorizado."}  -> HTTP 401
token subObjeto                               {"error":"No autorizado."}  -> HTTP 401
=== Usuario desactivado / con otro rol (mismo token valido)
  filas afectadas: 1
Valeria desactivada                           {"error":"No autorizado."}  -> HTTP 401
  filas afectadas: 1
Valeria reactivada                            {"error":"La ruta solicitada no existe."}  -> HTTP 404
  filas afectadas: 1
Valeria con rol supervisor                    {"error":"No autorizado."}  -> HTTP 401
  filas afectadas: 1
Valeria restaurada                            {"error":"La ruta solicitada no existe."}  -> HTTP 404
=== Consola de la API (no debe haber errores 500)
```

### CORS (S6)

```
preflight Origin http://127.0.0.1:5500    -> HTTP/1.1 204 No Content Access-Control-Allow-Origin: http://127.0.0.1:5500 
preflight Origin http://localhost:5500    -> HTTP/1.1 200 OK 
preflight Origin http://evil.test         -> HTTP/1.1 200 OK 
```

### L3 y L4 — Panel

Lo ajeno (S5), eliminar (L4.18) y acceso al catálogo:

```
== L3 catálogo
sin sesión GET /productos: HTTP 302 (redirigió http://127.0.0.1:8000/login) | title: Redirecting to http://127.0.0.1:8000/login
Sergio GET /productos: HTTP 403 | title: Forbidden
Alicia DELETE /productos/5: HTTP 405 | title: 
== L4 lo ajeno (Sergio sobre Abarrotes San Juan id 6, de Carlos; y id 9999)
[6] GET edit: HTTP 404 | title: Not Found
[6] PUT inválido: HTTP 404 | title: Not Found
[6] PUT válido: HTTP 404 | title: Not Found
[6] PATCH asignar: HTTP 404 | title: Not Found
[6] PATCH liberar: HTTP 404 | title: Not Found
[9999] GET edit: HTTP 404 | title: Not Found
[9999] PUT inválido: HTTP 404 | title: Not Found
[9999] PUT válido: HTTP 404 | title: Not Found
[9999] PATCH asignar: HTTP 404 | title: Not Found
[9999] PATCH liberar: HTTP 404 | title: Not Found
== L4 eliminar
botón «Eliminar» visible para Sergio: NO
Sergio DELETE Escuela Futuro (20, disponible): HTTP 403 | title: Forbidden
Alicia DELETE Agencia Cuatro Caminos (16): HTTP 200 (redirigió 302->http://127.0.0.1:8000/clientes) | title: Laravel | aviso: Cliente eliminado.
```

Validación junto al campo (L3.2, RP7, L7.1), L4.11 y paginación:

```
== L3 precio y unidades_por_caja (Alicia, formulario de alta)
precio=100000000: HTTP 200 (redirigió 302->http://127.0.0.1:8000/productos/create) | title: Laravel | junto al campo: {"precio":["El precio no puede ser mayor que Q99,999,999.99."]}
precio=abc: HTTP 200 (redirigió 302->http://127.0.0.1:8000/productos/create) | title: Laravel | junto al campo: {"precio":["Escriba un precio válido."]}
precio=0: HTTP 200 (redirigió 302->http://127.0.0.1:8000/productos/create) | title: Laravel | junto al campo: {"precio":["El precio debe ser mayor que cero."]}
precio=10.999: HTTP 200 (redirigió 302->http://127.0.0.1:8000/productos/create) | title: Laravel | junto al campo: {"precio":["Escriba el precio con dos decimales como máximo (por ejemplo, 1200.50)."]}
unidades_por_caja=0: HTTP 200 (redirigió 302->http://127.0.0.1:8000/productos/create) | title: Laravel | junto al campo: {"unidades_por_caja":["Las unidades por caja deben estar entre 1 y 999."]}
unidades_por_caja=1000: HTTP 200 (redirigió 302->http://127.0.0.1:8000/productos/create) | title: Laravel | junto al campo: {"unidades_por_caja":["Las unidades por caja deben estar entre 1 y 999."]}
unidades_por_caja=2.5: HTTP 200 (redirigió 302->http://127.0.0.1:8000/productos/create) | title: Laravel | junto al campo: {"unidades_por_caja":["Las unidades por caja deben ser un número entero."]}
== L4 editar Ferretería Xela (2) con campos vacíos (Alicia)
nombre vacío: HTTP 200 (redirigió 302->http://127.0.0.1:8000/clientes/2/edit) | title: Laravel | junto al campo: {"nombre":["Escriba el nombre del cliente."]}
nit vacío: HTTP 200 (redirigió 302->http://127.0.0.1:8000/clientes/2/edit) | title: Laravel | junto al campo: {"nit":["Escriba un NIT válido (con su dígito verificador) o CF."]}
telefono vacío: HTTP 200 (redirigió 302->http://127.0.0.1:8000/clientes/2/edit) | title: Laravel | junto al campo: {"telefono":["Escriba un teléfono de Guatemala de 8 dígitos."]}
== L4.11 Alicia elimina Comercial El Quetzal (con oportunidades)
HTTP 200 (redirigió 302->http://127.0.0.1:8000/clientes) | title: Laravel | [arriba] No puede eliminar un cliente que tiene oportunidades.
== L4.1 paginación (Alicia, 21 clientes)
Mostrando 1 a 20 de 21 resultados
> SELECT id,nombre,nit,telefono FROM clientes WHERE id IN (1,2)
  {"id":1,"nombre":"Comercial El Quetzal","nit":"4521817K","telefono":"55551234"}
  {"id":2,"nombre":"Ferretería Xela","nit":"98765434","telefono":"77612345"}
> SELECT COUNT(*) productos_evidencia FROM productos WHERE nombre LIKE 'Evidencia%'
  {"productos_evidencia":0}
```

### CHECK de `unidades_por_caja` (RP5) — SQL directo en una transacción revertida

```
> START TRANSACTION
  filas afectadas: 0
> INSERT INTO oportunidad_productos (oportunidad_id,producto_id,cantidad,precio_unitario,unidades_por_caja,created_at,updated_at) SELECT o.id,3,30,450,24,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM oportunidades o WHERE o.titulo='Seguimiento anual'
  ERROR 3819: Check constraint 'oportunidad_productos_caja_completa' is violated.
> INSERT INTO oportunidad_productos (oportunidad_id,producto_id,cantidad,precio_unitario,unidades_por_caja,created_at,updated_at) SELECT o.id,3,48,450,24,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM oportunidades o WHERE o.titulo='Seguimiento anual'
  filas afectadas: 1
> INSERT INTO productos (nombre,precio,unidades_por_caja,created_at,updated_at) VALUES ('chk0',1,0,UTC_TIMESTAMP(),UTC_TIMESTAMP())
  ERROR 3819: Check constraint 'productos_unidades_por_caja_rango' is violated.
> INSERT INTO productos (nombre,precio,unidades_por_caja,created_at,updated_at) VALUES ('chk1000',1,1000,UTC_TIMESTAMP(),UTC_TIMESTAMP())
  ERROR 3819: Check constraint 'productos_unidades_por_caja_rango' is violated.
> ROLLBACK
  filas afectadas: 0
> SELECT COUNT(*) lineas FROM oportunidad_productos
  {"lineas":13}
```

### L3.5 — Cambiar el precio no toca las líneas

```
-- antes
> SELECT op.id,o.titulo,op.cantidad,op.precio_unitario,op.unidades_por_caja FROM oportunidad_productos op JOIN oportunidades o ON o.id=op.oportunidad_id WHERE op.producto_id=2 ORDER BY op.id
  {"id":1,"titulo":"Renovación de planes","cantidad":2,"precio_unitario":"2600.00","unidades_por_caja":null}
  {"id":5,"titulo":"Ampliación a bodega","cantidad":1,"precio_unitario":"2750.50","unidades_por_caja":null}
  {"id":13,"titulo":"Cotización de equipo","cantidad":2,"precio_unitario":"2750.50","unidades_por_caja":null}
Alicia cambia Plan avanzado a Q9999.99 y caja 12: HTTP 200 (redirigió 302->http://127.0.0.1:8000/productos) | title: Laravel | aviso: Producto actualizado.
-- después
> SELECT op.id,o.titulo,op.cantidad,op.precio_unitario,op.unidades_por_caja FROM oportunidad_productos op JOIN oportunidades o ON o.id=op.oportunidad_id WHERE op.producto_id=2 ORDER BY op.id
  {"id":1,"titulo":"Renovación de planes","cantidad":2,"precio_unitario":"2600.00","unidades_por_caja":null}
  {"id":5,"titulo":"Ampliación a bodega","cantidad":1,"precio_unitario":"2750.50","unidades_por_caja":null}
  {"id":13,"titulo":"Cotización de equipo","cantidad":2,"precio_unitario":"2750.50","unidades_por_caja":null}
> SELECT precio,unidades_por_caja FROM productos WHERE id=2
  {"precio":"9999.99","unidades_por_caja":12}
> SELECT COUNT(*) ventas, SUM((SELECT SUM(op.cantidad*op.precio_unitario) FROM oportunidad_productos op WHERE op.oportunidad_id=o.id)) total FROM oportunidades o WHERE o.vendedor_id=4 AND o.etapa='ganada' AND DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR,'%Y-%m')='2026-10'
  {"ventas":2,"total":"9250.00"}
```

### F1 — SPA (Edge sin ventana, 375 px)

`ls` es el contenido de `localStorage` (valor recortado); `scrollW`/`clientW` es el ancho del documento.

```
inicio (375px) -> {"alerta":null,"h":"Aguaviva","correo":"","ls":{},"scrollW":375,"clientW":375}
Sergio -> {"alerta":"La cuenta no tiene acceso a la aplicación de vendedores.","h":"Aguaviva","correo":"","ls":{},"scrollW":375,"clientW":375}
Valeria clave mala -> {"alerta":"El correo o la clave son incorrectos.","h":"Aguaviva","correo":"","ls":{},"scrollW":375,"clientW":375}
Valeria ok -> {"alerta":null,"h":"Distribuidora Aguaviva","ls":{"crm_agua_token":"eyJhbGciOiJIUzI1NiIsInR5c…"},"scrollW":375,"clientW":375}
tras recargar -> {"alerta":null,"h":"Distribuidora Aguaviva","ls":{"crm_agua_token":"eyJhbGciOiJIUzI1NiIsInR5c…"},"scrollW":375,"clientW":375}
tras cerrar sesión -> {"alerta":null,"h":"Aguaviva","correo":"","ls":{},"scrollW":375,"clientW":375}
XSS en correo: diálogos alert abiertos = 0
F1.4: token alterado en plena sesión, GET /api/clientes -> status 401 | {"alerta":"Su sesión ya no es válida. Inicie sesión nuevamente.","h":"Aguaviva","correo":"","ls":{},"scrollW":375,"clientW":375}
API :3000 -> 000
API apagada -> {"alerta":"No se pudo conectar con la API. Compruebe que esté en marcha.","h":"Aguaviva","correo":"","ls":{},"scrollW":375,"clientW":375}
```

### `php artisan test` (salida de `vendor/bin/phpunit --testdox`)

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.27
Configuration: C:\Users\cuenta\Documents\6to Semestre\Progra Web\Proyecto Final\laravel\phpunit.xml

Time: 00:11.940, Memory: 66.00 MB

Acceso Panel (Tests\Feature\AccesoPanel)
 ✔ El administrador entra
 ✔ El supervisor entra
 ✔ El vendedor no entra y se le envia a la spa
 ✔ Una cuenta desactivada no entra
 ✔ Una clave incorrecta no entra
 ✔ Una cuenta desactivada pierde la sesion en la siguiente peticion
 ✔ Un vendedor con sesion abierta es expulsado
 ✔ El panel no exige verificar el correo

Authentication (Tests\Feature\Auth\Authentication)
 ✔ Login screen can be rendered
 ✔ Users can authenticate using the login screen
 ✔ Users can not authenticate with invalid password
 ✔ Users can logout

Clientes (Tests\Feature\Clientes)
 ✔ Recorre los casos de normalizacion compartidos
 ✔ Crea cliente normalizado y acepta varios cf
 ✔ Rechaza datos invalidos y duplicados normalizados
 ✔ Lista busca por nombre nit y filtra por vendedor o disponible
 ✔ Supervisor no ve clientes ajenos y admin ve todos
 ✔ Asigna y no permite el cliente numero once
 ✔ No asigna a vendedor desactivado
 ✔ Reasigna solo oportunidades abiertas y no toca actividades
 ✔ Libera clientes sin oportunidades abiertas y rechaza los demas
 ✔ Elimina solo clientes sin oportunidades y borra sus actividades
 ✔ Vendedor no entra al panel de clientes
 ✔ Dos asignaciones no superan el maximo de cartera
 ✔ Lo ajeno responde 404 antes de validar en cada accion
 ✔ Editar exige los campos y responde en espanol
 ✔ La paginacion esta en espanol
 ✔ Solo el administrador elimina clientes
 ✔ Asignar y liberar bloquean primero users y luego clientes
 ✔ Las demas puertas con formrequest rechazan al rol antes de validar

Clientes Semilla (Tests\Feature\ClientesSemilla)
 ✔ L4 1 y l4 2 respetan la visibilidad y los filtros
 ✔ L4 3 l4 4 l4 5 y l4 6 validan identidad
 ✔ L4 7 rechaza la cartera numero once
 ✔ L4 8 reasigna abiertas y conserva cerradas e historial
 ✔ L4 9 rechaza liberar con oportunidades abiertas
 ✔ L4 13 libera cliente con solo oportunidades cerradas
 ✔ L4 15 y l4 16 respetan vendedores desactivados
 ✔ L4 17 reactivar vendedor no mueve su cartera
 ✔ L4 14 editar con valores repetidos falla y sin cambios no choca
 ✔ L4 10 y l4 11 controlan eliminacion
 ✔ L4 12 no permite asignar a vendedor desactivado

Cuentas (Tests\Feature\Cuentas)
 ✔ Una cuenta nueva creada por el administrador entra de inmediato
 ✔ Un vendedor se crea con su supervisor
 ✔ Un vendedor sin supervisor o con uno inactivo se rechaza
 ✔ Un supervisor enviado a un administrador o supervisor queda vacio
 ✔ El administrador no puede desactivarse
 ✔ El administrador no puede quitarse el rol
 ✔ No se cambia el rol de un supervisor con vendedores
 ✔ No se cambia el rol de un vendedor con clientes
 ✔ Si se puede desactivar a un supervisor con equipo
 ✔ Editar sin contrasena no cambia el hash
 ✔ Los mensajes y los datos escritos vuelven al formulario
 ✔ Las pantallas de alta y edicion se muestran
 ✔ Un supervisor no administra cuentas
 ✔ Las cuentas no se borran
 ✔ La lista escapa los nombres

Email Verification (Tests\Feature\Auth\EmailVerification)
 ✔ Email verification screen can be rendered
 ✔ Email can be verified
 ✔ Email is not verified with invalid hash

Example (Tests\Feature\Example)
 ✔ The application returns a successful response

Example (Tests\Unit\Example)
 ✔ That true is true

Password Confirmation (Tests\Feature\Auth\PasswordConfirmation)
 ✔ Confirm password screen can be rendered
 ✔ Password can be confirmed
 ✔ Password is not confirmed with invalid password

Password Reset (Tests\Feature\Auth\PasswordReset)
 ✔ Reset password link screen can be rendered
 ✔ Reset password link can be requested
 ✔ Reset password screen can be rendered
 ✔ Password can be reset with valid token

Password Update (Tests\Feature\Auth\PasswordUpdate)
 ✔ Password can be updated
 ✔ Correct password must be provided to update password

Productos (Tests\Feature\Productos)
 ✔ El administrador puede crear y editar un producto
 ✔ Rechaza precios invalidos
 ✔ Precio maximo y un solo mensaje por campo
 ✔ Rechaza nombres duplicados
 ✔ Desactiva sin eliminar fisicamente
 ✔ Cambiar precio no modifica el precio historico
 ✔ Valida unidades por caja
 ✔ Solo el administrador accede al catalogo
 ✔ No existe eliminacion fisica

Profile (Tests\Feature\Profile)
 ✔ Profile page is displayed
 ✔ Profile information can be updated
 ✔ Email verification status is unchanged when the email address is unchanged
 ✔ Un usuario no puede borrar su cuenta desde el perfil
 ✔ El perfil no ofrece borrar la cuenta

Registration (Tests\Feature\Auth\Registration)
 ✔ La pagina de registro no existe
 ✔ No se puede enviar un registro
 ✔ No existe la ruta con nombre register
 ✔ El login no enlaza a un registro

OK (88 tests, 460 assertions)
```

## Bitácora de IA (P5)
<!-- Al menos tres casos: qué propuso el agente, cómo se detectó, commit que lo corrigió. -->

### Caso 1 — Archivos restaurados sin que se pidiera

- **Fecha:** 2026-11-08 · **Integrante:** A
- **Qué falló:** antes del commit base, el agente restauró `config/app.php`, `config/database.php`,
  `.env.example` y `DatabaseSeeder.php` a la versión original de Laravel sin que se le pidiera. El
  commit `553dbbc`, que dice «MySQL y zona horaria UTC», no contenía esos cambios.
- **Cómo se detectó:** el agente comparó el contenido de `553dbbc` con lo esperado y lo reportó.
- **Corrección:** commit `ba05381` («laravel: configura MySQL y zona horaria UTC»).
- **Lección:** se agregó a `CLAUDE.md` la regla: «No restaures, reviertas ni sobrescribas archivos
  que no hayas creado en la tarea actual sin preguntarme antes. Si quieres dejar el árbol limpio para
  un commit, muéstrame `git diff` y espera mi OK. Antes de cada commit, compara su contenido con lo que
  promete el mensaje.»

> **Nota sobre los commits del bloque 2.** El bloque 2 llegó primero en un commit local, `e3273d5`
> («Implementa catálogo, clientes y acceso API»), **sin prefijo y con todo el bloque en uno solo**. Ese
> commit se descartó al reiniciar la rama `bloque-3/equipos-reportes-cartera` a
> `origin/feat/bloque-2-api-base`, y hoy no está en ninguna rama (solo en el reflog local). El mismo
> código (diff vacío contra `e3273d5`) está en cinco commits con prefijo: `ab90dd6`, `3eddf1d`,
> `b5da01f`, `f63434a` y `f897d78`. Este último usa el prefijo `frontend:` en lugar de `spa:`
> (plan, sección 4). Los casos 2 a 5 citan esos commits, que son los que están en el historial.
>
> **Cómo se detectaron los casos 2 a 5:** todos por una auditoría de solo lectura del bloque 2
> (2026-11-08) que probó la API real con `curl.exe`, el panel con peticiones HTTP y la SPA en un
> navegador. Las pruebas automáticas existentes (81, todas en verde) **no** detectaron ninguno. Cada
> corrección agregó la prueba que faltaba.

### Caso 2 — Middleware de autenticación escrito pero nunca montado

- **Fecha:** 2026-11-08 · **Integrante:** B
- **Qué propuso el agente:** escribió `api/src/middleware/autenticacion.js` (verifica el JWT con
  HS256 y relee al usuario), pero no lo importó ni lo montó en `api/src/app.js`. El commit que lo trajo
  se llama «api: autenticacion JWT y middleware de vendedores», y el de `e3273d5` decía «acceso API».
- **Por qué es incorrecto:** ninguna ruta quedaba protegida, así que A2.1–A2.3 no se cumplían:
  `GET /api/clientes` sin token respondía 404 en vez de 401. El mensaje del commit prometía algo que el
  código no hacía (regla de `CLAUDE.md`: comparar el commit con lo que promete el mensaje).
- **Cómo se detectó:** auditoría de solo lectura; probó la API real y encontró 404 donde esperaba 401.
  `grep autenticacion api/src` solo encontraba la definición. Las pruebas automáticas no cubren la API.
- **Commit que lo introdujo:** `3eddf1d` (en el historial descartado, `e3273d5`).
- **Corrección:** commit `bfcdee2` («api: montar middleware de autenticación (A2)»). Monta el
  middleware sobre `/api` salvo `POST /api/auth/login` y responde 401 a un token sin `sub` o con `sub`
  no numérico (antes daba 500). Probado contra la API real.
- **Lección:** un middleware solo cuenta si una petición real lo atraviesa. La evidencia de A2 se toma
  contra la API levantada, no leyendo el archivo.

### Caso 3 — Validar antes de autorizar confirmaba que un cliente ajeno existía

- **Fecha:** 2026-11-08 · **Integrante:** B
- **Qué propuso el agente:** en `ClienteController::update`, la comprobación de visibilidad
  (`autorizarVisibilidad`, 404) corría dentro del método. Laravel resuelve y valida
  `GuardarClienteRequest` antes de entrar al método, y su `authorize()` devolvía siempre `true`.
- **Por qué es incorrecto:** un supervisor que enviaba un `PUT` con datos inválidos sobre un cliente
  de otro equipo recibía 302 con errores; con un id inexistente recibía 404. La diferencia confirma
  que el cliente existe, y viola R2 y S5 («lo ajeno responde 404»).
- **Cómo se detectó:** auditoría de solo lectura. Con datos válidos el resultado era 404, pero con
  datos inválidos era 302. Las pruebas existentes solo probaban la URL ajena con datos válidos.
- **Commit que lo introdujo:** `f63434a`.
- **Corrección:** commit `241d490` («laravel: autorizar antes de validar»).
  `GuardarClienteRequest::authorize()` responde 404 antes de validar, con la regla compartida
  `Cliente::esVisiblePara()`. Se revisaron los demás FormRequest y acciones con `validate()`.
- **Lección:** en un FormRequest, la autorización va en `authorize()`; el controlador llega tarde.

### Caso 4 — El supervisor podía eliminar clientes

- **Fecha:** 2026-11-08 · **Integrante:** B
- **Qué propuso el agente:** puso la ruta `DELETE /clientes/{cliente}` en el grupo
  `rol:administrador,supervisor` y mostró el botón «Eliminar» a los dos roles.
- **Por qué es incorrecto:** el enunciado (L4) dice «**El administrador** elimina un cliente solo si
  no tiene oportunidades», y en la tabla de roles el supervisor no tiene esa acción. spec-v1 solo lo
  prevé para Alicia (L4.10, L4.11). El agente lo supuso sin preguntar.
- **Cómo se detectó:** auditoría de solo lectura. Sergio eliminó un cliente disponible desde el panel
  (respuesta «Cliente eliminado.»). Las pruebas existentes solo probaban la eliminación como Alicia.
- **Commit que lo introdujo:** `f63434a`.
- **Corrección:** commit `62b4f31` («laravel: solo el administrador elimina clientes (L4)»): ruta en
  el grupo `rol:administrador` (supervisor: 403), comprobación en `destroy()` y botón solo para el
  administrador. La spec lo deja escrito en la versión 1.2 (L4.18), commit `563e6ce`.
- **Lección:** cuando la spec nombra un actor («Alicia lo elimina»), la ruta se restringe a ese rol y
  se prueba también con el rol que no debe poder.

### Caso 5 — Al editar, el nombre vacío se guardaba

- **Fecha:** 2026-11-08 · **Integrante:** B
- **Qué propuso el agente:** en `GuardarClienteRequest`, al editar, los campos eran `sometimes` en vez
  de `required`, copiando P-7 (`PATCH` parcial de la API) a un formulario del panel que siempre envía
  todos los campos. `prepareForValidation` convertía el nombre vacío en `''`, que `sometimes` dejaba pasar.
- **Por qué es incorrecto:** la spec 3.4 dice que `clientes.nombre` es obligatorio, de 1 a 150
  caracteres. Ferretería Xela quedó guardada con nombre vacío. Además, un NIT o teléfono vacío mostraba
  «The nit field must be a string.», en inglés (L7.1).
- **Cómo se detectó:** auditoría de solo lectura (`PUT` con `nombre=""`, y en la base `"nombre":""`).
  Al escribir la prueba de la corrección apareció otro defecto, esta vez detectado **por prueba**: un
  campo enviado como arreglo daba 500 («Array to string conversion»).
- **Commit que lo introdujo:** `f63434a`.
- **Corrección:** commit `1fff582` («laravel: nombre obligatorio al editar y mensajes en español
  (L7)»): campos obligatorios también al editar, `bail` (un mensaje por campo), solo se normalizan
  textos, y se agregan `lang/es` y `APP_LOCALE=es`.
- **Lección:** una regla de una puerta (P-7 es de la API) no se copia a la otra sin que la spec lo diga.
