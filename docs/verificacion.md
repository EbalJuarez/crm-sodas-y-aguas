# Verificación

## Cierre del bloque 4: A7, concurrencia y L7

Evidencia ejecutada en `crm_agua_test` el 2026-11-08, sin modificar `crm_agua`:

- **A7:** `npm test` pasó 9 pruebas. La API real confirmó `401` sin token, `404` autenticado en una
  ruta inexistente, `422` de validación, `400` para `estado` y `mes` inválidos, y `400` con cuerpo
  ausente. Las pruebas de middleware cubren además JSON mal formado, `413` por más de 100 KB y
  `500` genérico sin filtrar el mensaje SQL.
- **A3.8:** se agregó `ConcurrenciaSeeder` fuera de `DatabaseSeeder`; `concurrencia-completa.mjs`
  dio exactamente 1×200 y 9×409 entre diez vendedores para el mismo cliente.
- **A3.9:** dos tomas y un alta simultáneos de Carlos dieron un solo éxito (`201` en esa corrida) y
  2×409; la cartera terminó en 10/10.
- **S8:** dos altas simultáneas con el mismo NIT dieron 1×201 y 1×409. La prueba panel/API se
  ejecutó en tres rondas: API 201, panel 302, sin 500 y con dueño de oportunidad consistente.
- **L7.1:** se tradujeron dashboard, autenticación, perfil, recuperación, confirmación, verificación,
  errores 403/404/419 y validaciones; `APP_LOCALE=es` y `APP_FALLBACK_LOCALE=es` quedaron en
  `.env.example`. `php artisan view:cache` pasó.
- **L7.2:** `php artisan test` (111 pruebas, 749 aserciones) y `npm test` recorren los mismos casos
  de normalización y mantienen los mensajes equivalentes en ambas puertas.

La base de prueba se restauró al final con `php artisan migrate:fresh --seeder=VerificacionSeeder`.

## Correcciones críticas del bloque 4

Evidencia adicional obtenida sobre `crm_agua_test`, sin tocar `crm_agua`:

- **S8 interbloqueo:** `POST /api/oportunidades` bloquea primero `users` y después `clientes`, igual que
  la liberación del panel. `api/scripts/concurrencia-s8.mjs` lanzó las dos peticiones HTTP reales en
  paralelo y se repitió en tres rondas, reiniciando `crm_agua_test` antes de cada una. Las tres dieron
  API `201`, panel `302`, ningún `500`, cliente conservado en vendedor 4 y una oportunidad abierta de
  vendedor 4; D2 y D3 se mantuvieron.
- **413:** `npm test` comprueba un cuerpo JSON mayor de 100 KB (`413` con `{ error }`) y un JSON roto
  (`400` con `{ error }`). El error inesperado conserva el `500` genérico.
- **U+FEFF:** el caso `452\uFEFF1817K` y `5555\uFEFF1234` está en `docs/casos-normalizacion.json`;
  `npm test` y `php artisan test` recorren el mismo archivo y dan inválido en ambas puertas.
- **DELETE ajeno:** la ruta de borrado ya no usa model binding implícito antes del middleware de rol.
  La prueba Laravel verifica que Sergio recibe `403` tanto para un cliente existente como para el id
  inexistente `999999`; Alicia conserva el borrado permitido y el `404` administrativo para ids no encontrados.

## Cierre A8 y C.1

- **A8.1:** `README.md` documenta requisitos, bases, variables, migraciones, las tres piezas, pruebas,
  cuentas de semilla y la tabla completa de endpoints con sus errores principales.
- **A8.2:** `docs/api.postman_collection.json` es JSON válido de Postman v2.1, tiene variables para la
  URL, token e ids, una petición por endpoint y scripts que comprueban códigos de éxito y error. La
  importación y ejecución dentro de la aplicación Postman queda como comprobación manual de la pareja.
- **C.1:** en `crm_agua_test`, `migrate:fresh --seed` ejecutó ambas semillas. El resultado fue 10 usuarios,
  11 productos, 27 clientes, 13 oportunidades, 17 líneas, 47 historiales y 8 actividades; hay una
  oportunidad en cada una de las seis etapas, dos productos con caja completa y cero líneas con múltiplo
  inválido. Ningún vendedor superó 10 clientes y no se repitieron correos, NIT ni teléfonos.

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
| A3.1 | API real: Valeria `GET /clientes` da sus 3; `?q=Xela` solo Ferretería Xela; `?q=4521817` Comercial El Quetzal («Bloque 3», A3) | ✔ |
| A3.2 | API real: `GET /clientes/disponibles` da los 6, cada uno solo `{ id, nombre }` | ✔ |
| A3.3 | API real: `GET` y `PATCH` de un cliente de Diego y de un id inexistente dan el mismo 404 `{"error":"No se encontró el cliente."}`, también con cuerpo inválido o `{}` (404 antes de validar) | ✔ |
| A3.4 | API real: `POST /clientes` válido da 201 con la forma `Cliente` (`vendedor_id` del cuerpo ignorado) | ✔ |
| A3.5 | API real: NIT `452 1817-k` y teléfono `+502 5555-1234` dan 409 | ✔ |
| A3.6 | API real: `{}` y datos inválidos dan 422 con un mensaje por campo (textos de 3.4) | ✔ |
| A3.7 | API real: Carlos (9 de 10) crea un cliente (201) y luego tomar y crear dan 409 «Su cartera ya tiene 10 clientes, el máximo permitido.» | ✔ |
| A3.8 | `ConcurrenciaSeeder` + `scripts/concurrencia-completa.mjs`: diez vendedores toman Gimnasio Fuerza simultáneamente; 1×200 y 9×409 | ✔ |
| A3.9 | `scripts/concurrencia-completa.mjs`: dos tomas y un alta simultáneos de Carlos; un solo éxito, 2×409 y cartera final 10/10 | ✔ |
| A3.10 | API real: tomar un cliente de Diego da 409; un id inexistente, 404 | ✔ |
| A3.11 | API real: Comercial El Quetzal y María Coyoy con la forma `Cliente`; María Coyoy con `"nit":"CF"` y `"correo":null` | ✔ |
| A3.12–A3.17 | API real: cada caso con el código y el cuerpo esperados (ver la salida de A3); A3.13 comprobado además con SQL directo: `nit` queda NULL | ✔ |
| A3.18 | API real: `?q=4521817-k` y `?q=452 1817K` dan Comercial El Quetzal; también desde la SPA | ✔ |
| A4.1–A4.13 | API real: secuencia de A4 («Bloque 3», A4); A4.13 deja el historial igual (49 antes y después) | ✔ |
| A4.14 | `scripts/concurrencia.mjs` (d): dos `PATCH /etapa` simultáneos dan 1×200 y 1×409; el historial queda con 2 registros | ✔ |
| A4.15–A4.22 | API real: secuencia de A4; A4.10 deja el historial igual (53 antes y después); A4.21 da 204 sin cuerpo | ✔ |
| RP1–RP4 | API real: Gaseosa lata 355 ml (caja 24) creada con el modelo del panel (`Producto::create`, como el formulario del catálogo): 30 → 422, 48 → 201 con la copia `unidades_por_caja: 24`, `PATCH` 36 → 422, 72 → 200; con el catálogo en 12, la línea vieja rechaza 60 y una nueva lo acepta. La semilla sectorial actual agrega productos equivalentes para demostración. | ✔ |
| RP6 | API real: con solo `VerificacionSeeder`, A4.4 da 201; las cifras de control con la semilla sola no cambian (abajo) | ✔ |
| L5.1 | `EquiposTest`: Sergio sobre Librería Cantel (Carlos) y Hotel Los Pinos (Iván) recibe 404 en editar, actualizar (válido e inválido), asignar y liberar; el cliente no cambia | ✔ |
| L5.2 | `EquiposTest`: Sergio no asigna a Carlos, Iván, Sandra ni Alicia (mensaje junto a `vendedor_id`); sí a Valeria | ✔ |
| L5.3 | `EquiposTest` y consulta al servicio: «Mi equipo» de Sergio, octubre: Valeria 3 de 10, 2 ventas, 9250.00; Diego 2 de 10, 1 venta, 6000.00 | ✔ |
| L5.4 | `EquiposTest`: Sergio no ve a Carlos ni a Iván en clientes, pedidos por cerrar ni «Mi equipo», ni filtrando por el id de Carlos | ✔ |
| L6.1–L6.4 | `ReporteMesTest`: 11 oportunidades para Alicia, 9 para Sergio, filtros, solo lectura (POST 405, PATCH 404) y *Equipo para sucursal* con cierre 31/10/2026 21:30 | ✔ |
| L6.5–L6.7 | `ReporteMesTest` y consulta a `ReporteMes` sobre `crm_agua`: todas las cifras de control coinciden (abajo) | ✔ |
| L7.1 | Vistas del panel traducidas, validaciones en español, `APP_LOCALE=es`, `view:cache` y `php artisan test` sin regresiones | ✔ |
| L7.2 | `ClientesTest` y `npm test` recorren el mismo `docs/casos-normalizacion.json`; NIT, teléfono, correo, duplicados y cartera llena conservan resultados equivalentes | ✔ |
| S3 | API real: `?q=' OR 1=1 -- ` da 200 `[]`; `?q=%` y `?q=_` se buscan como texto (API y `ClientesTest` del panel); `?q[]=x` da 400 en las dos puertas | ✔ |
| S5 | API: A3.3 y A4.15 (404); panel: `EquiposTest` (Sergio contra clientes de Sandra) | ✔ |
| S7 (SPA) | SPA en Edge sin ventana: el cliente `<img src=x onerror=alert(1)>` se ve como texto, no se crea ningún `<img>` y no se llama a `alert` | ✔ |
| S8 | A3.8, A3.9, dos altas con el mismo NIT, A4.14 y la prueba panel/API de asignación concurrente; no hubo HTTP 500 ni interbloqueos | ✔ |
| F1.4 | SPA real (F2): un token alterado al recargar y una cuenta desactivada a media sesión vuelven al login con «Su sesión venció o su cuenta ya no tiene acceso…»; el 401 del login no cuenta como sesión vencida | ✔ |
| F2.1–F2.5 | SPA en Edge sin ventana a 375 px contra la API real (salida de F2 abajo) | ✔ |
| F2 (I2) | **Pendiente.** No se probó que un cliente asignado en el panel aparezca en la SPA (hito I2 del plan) | Pendiente |
| F6.2, F6.3, F6.5 (vistas de F2) | SPA: indicador de carga visible durante una petición; con la API apagada, mensaje con «Reintentar»; `scrollWidth ≤ innerWidth` a 375 px en login, cartera, alta con errores y disponibles | ✔ |

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

`php artisan migrate:fresh --seed` carga `VerificacionSeeder` y `SectorSeeder` sin pasos manuales. La
semilla completa deja 10 usuarios, 11 productos, 27 clientes, 13 oportunidades, 17 líneas, 47
historiales y 8 actividades. La comprobación SQL mostró las seis etapas y ningún vendedor por encima
de 10 clientes; la semilla de verificación sola conserva los conteos oficiales de L1.4.

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

## Bloque 3 (A3, A4, L5, L6, L7, F2)

Evidencia obtenida el 2026-11-08 sobre el commit `6a0b42a` (bloque 3: `7dbbf5f`, `e3d89a5`, `fe36cd2`,
`c9e31bc`, `c73747a`, `510138c`, `c7869d3` y `6a0b42a`), con **MySQL 8.0.40** (falta repetirla en
MySQL 8.4 antes de la entrega). La base se recargó con
`php artisan migrate:fresh --seeder=VerificacionSeeder` antes de cada bloque de pruebas y al terminar.

Cómo se obtuvo:

- **API real**, levantada desde `api/` con `node src/server.js` y las variables en el entorno del
  proceso (`DB_*` de `laravel/.env`, `JWT_SECRET` aleatorio de 64 caracteres, `JWT_EXPIRES_IN=8h`,
  `CORS_ORIGIN=http://127.0.0.1:5500`); no se creó `api/.env`. Peticiones con `curl.exe` desde
  PowerShell; las respuestas largas se recortan con «…» en la salida.
- **Concurrencia:** `node scripts/concurrencia.mjs` desde `api/`, con la contraseña pública en
  `CLAVE_SEMILLA` (no está escrita en el script).
- **Panel:** solo con pruebas automáticas (`vendor/bin/phpunit --testdox`) y consultas al servicio
  `ReporteMes`; no se recorrió el panel en un navegador en este bloque.
- **SPA:** servida en `http://127.0.0.1:5500` (servidor estático de prueba, no Live Server) y
  manejada con Edge sin ventana (CDP) a 375 px, contra la API real.

**No probado en este bloque** (también en la matriz): A3.8 con `ConcurrenciaSeeder` (la semilla no
existe todavía), A3.9, dos altas simultáneas con el mismo NIT (S8), el hito I2 (cliente asignado en
el panel que aparece en la SPA), la tabla cruzada de L7.2, y todo en MySQL 8.4.

### Concurrencia (`node scripts/concurrencia.mjs`)

(b) se ejecuta antes que (a): si Carlos ganara la toma de (a), llegaría a 10 y (b) ya no podría dar
ningún 200.

```
Carlos tiene 9 de 10; hay 6 clientes disponibles.

(b) 5 tomas simultáneas de clientes distintos por Carlos (9 de 10)
  obtenido: {"200":1,"409":4}  esperado: {"200":1,"409":4}  → OK
    Carlos   200  Agencia Cuatro Caminos (id 16)
    Carlos   409  Su cartera ya tiene 10 clientes, el máximo permitido.
    Carlos   409  Su cartera ya tiene 10 clientes, el máximo permitido.
    Carlos   409  Su cartera ya tiene 10 clientes, el máximo permitido.
    Carlos   409  Su cartera ya tiene 10 clientes, el máximo permitido.

(a) 10 tomas simultáneas de «Café Zunil» entre Valeria, Diego y Carlos
  obtenido: {"200":1,"409":9}  esperado: {"200":1,"409":9}  → OK
    Valeria  200  Café Zunil (id 17)
    Diego    409  Este cliente ya no está disponible.
    Carlos   409  Su cartera ya tiene 10 clientes, el máximo permitido.
    Valeria  409  Este cliente ya no está disponible.
    Diego    409  Este cliente ya no está disponible.
    Carlos   409  Su cartera ya tiene 10 clientes, el máximo permitido.
    Valeria  409  Este cliente ya no está disponible.
    Diego    409  Este cliente ya no está disponible.
    Carlos   409  Su cartera ya tiene 10 clientes, el máximo permitido.
    Valeria  409  Este cliente ya no está disponible.

(c) 5 altas simultáneas con el teléfono 96470167
  obtenido: {"201":1,"409":4}  esperado: {"201":1,"409":4}  → OK
    Valeria  201  Prueba de concurrencia 1 (id 22)
    Valeria  409  Ya existe un cliente con ese teléfono.
    Valeria  409  Ya existe un cliente con ese teléfono.
    Valeria  409  Ya existe un cliente con ese teléfono.
    Valeria  409  Ya existe un cliente con ese teléfono.

(d) 2 cambios de etapa simultáneos de «Prueba de concurrencia de etapa 1794178223958»
  obtenido: {"200":1,"409":1}  esperado: {"200":1,"409":1}  → OK
    Valeria  200  etapa calificado
    Valeria  409  Ese cambio de etapa no está permitido.
  historial (2 registros; se esperan 2):
    null       → prospecto  Valeria López  2026-11-08T22:50:23.000Z
    prospecto  → calificado Valeria López  2026-11-08T22:50:23.000Z

Todas las pruebas dieron lo esperado.
Para restaurar la base (desde laravel/): php artisan migrate:fresh --seeder=VerificacionSeeder
```

### A3 — Clientes y cartera (API real, `curl.exe`)

```
A2.1 sin token                                             401  {"error":"No autorizado."}
A3.1 GET /clientes                                         200  [{"id":1,"nombre":"Comercial El Quetzal","nit":"4521817K","telefono":"55551234","correo":"compras@elquetzal.test"},{"id":2,"nombre":"Ferretería Xela","nit":"98765434","telefono":"77612345","correo":null},{"id":3,"nombre":"María Coyoy","nit":"CF","telefono":"41234567","correo":null}]
A3.1 ?q=Xela                                               200  [{"id":2,"nombre":"Ferretería Xela","nit":"98765434","telefono":"77612345","correo":null}]
A3.1 ?q=4521817                                            200  [{"id":1,"nombre":"Comercial El Quetzal","nit":"4521817K","telefono":"55551234","correo":"compras@elquetzal.test"}]
A3.18 ?q=4521817-k                                         200  [{"id":1,"nombre":"Comercial El Quetzal","nit":"4521817K","telefono":"55551234","correo":"compras@elquetzal.test"}]
A3.18 ?q=452 1817K                                         200  [{"id":1,"nombre":"Comercial El Quetzal","nit":"4521817K","telefono":"55551234","correo":"compras@elquetzal.test"}]
S3 ?q=' OR 1=1 --                                          200  []
?q=% (literal)                                             200  []
?q=_ (literal)                                             200  []
?q=a&q=b (arreglo)                                         400  {"error":"El parámetro q debe ser un solo texto."}
?q[]=x (arreglo)                                           400  {"error":"El parámetro q debe ser un solo texto."}
A3.2 disponibles                                           200  [{"id":16,"nombre":"Agencia Cuatro Caminos"},{"id":17,"nombre":"Café Zunil"},{"id":18,"nombre":"Clínica San Rafael"},{"id":19,"nombre":"Constructora Altiplano"},{"id":20,"nombre":"Escuela Futuro"},{"id":21,"nombre":"Gimnasio Fuerza"}]
A3.3 GET de Diego                                          404  {"error":"No se encontró el cliente."}
A3.3 GET inexistente                                       404  {"error":"No se encontró el cliente."}
A3.3 PATCH de Diego                                        404  {"error":"No se encontró el cliente."}
A3.3 PATCH inexistente                                     404  {"error":"No se encontró el cliente."}
PATCH de Diego con cuerpo invalido (404 antes)             404  {"error":"No se encontró el cliente."}
PATCH de Diego con {} (404 antes)                          404  {"error":"No se encontró el cliente."}
GET /clientes/abc                                          400  {"error":"El identificador del cliente no es válido."}
A3.11 Comercial El Quetzal                                 200  {"id":1,"nombre":"Comercial El Quetzal","nit":"4521817K","telefono":"55551234","correo":"compras@elquetzal.test"}
A3.11 María Coyoy                                          200  {"id":3,"nombre":"María Coyoy","nit":"CF","telefono":"41234567","correo":null}
A3.12 telefono 7761-2399                                   200  {"id":2,"nombre":"Ferretería Xela","nit":"98765434","telefono":"77612399","correo":null}
A3.13 nit cf                                               200  {"id":2,"nombre":"Ferretería Xela","nit":"CF","telefono":"77612399","correo":null}
A3.14 telefono de Farmacia                                 409  {"error":"Ya existe un cliente con ese teléfono."}
A3.14 nit propio                                           200  {"id":2,"nombre":"Ferretería Xela","nit":"98765434","telefono":"77612399","correo":null}
A3.14 nit propio otra vez (no choca)                       200  {"id":2,"nombre":"Ferretería Xela","nit":"98765434","telefono":"77612399","correo":null}
A3.15 nit 4521817-1                                        422  {"errores":{"nit":"Escriba un NIT válido (con su dígito verificador) o CF."}}
A3.15 nombre espacios                                      422  {"errores":{"nombre":"Escriba el nombre del cliente."}}
A3.16 {}                                                   400  {"error":"Envíe al menos uno de estos campos: nombre, nit, telefono, correo."}
A3.16 telefno                                              400  {"error":"Envíe al menos uno de estos campos: nombre, nit, telefono, correo."}
PATCH arreglo                                              400  {"error":"Envíe al menos uno de estos campos: nombre, nit, telefono, correo."}
PATCH sin cuerpo                                           400  {"error":"Falta el cuerpo de la petición. Envíelo en formato JSON."}
PATCH JSON roto                                            400  {"error":"El cuerpo JSON no tiene un formato válido."}
A3.17 nombre + vendedor_id                                 200  {"id":2,"nombre":"Ferretería Xela Centro","nit":"98765434","telefono":"77612399","correo":null}
A3.17 sigue en su cartera                                  200  {"id":2,"nombre":"Ferretería Xela Centro","nit":"98765434","telefono":"77612399","correo":null}
PATCH correo null                                          200  {"id":1,"nombre":"Comercial El Quetzal","nit":"4521817K","telefono":"55551234","correo":null}
PATCH correo invalido                                      422  {"errores":{"correo":"Escriba un correo válido."}}
A3.4 POST valido                                           201  {"id":22,"nombre":"Tienda Nueva","nit":"19","telefono":"22334455","correo":"tienda@nueva.test"}
A3.5 POST nit 452 1817-k                                   409  {"error":"Ya existe un cliente con ese NIT."}
A3.5 POST telefono +502 5555-1234                          409  {"error":"Ya existe un cliente con ese teléfono."}
A3.6 POST {}                                               422  {"errores":{"nombre":"Escriba el nombre del cliente.","nit":"Escriba un NIT válido (con su dígito verificador) o CF.","telefono":"Escriba un teléfono de Guatemala de 8 dígitos."}}
A3.6 POST invalidos                                        422  {"errores":{"nombre":"Escriba el nombre del cliente.","nit":"Escriba un NIT válido (con su dígito verificador) o CF.","telefono":"Escriba un teléfono de Guatemala de 8 dígitos.","correo":"Escriba un correo válido."}}
POST tipos raros                                           422  {"errores":{"nombre":"Escriba el nombre del cliente.","nit":"Escriba un NIT válido (con su dígito verificador) o CF.","telefono":"Escriba un teléfono de Guatemala de 8 dígitos."}}
POST sin cuerpo                                            400  {"error":"Falta el cuerpo de la petición. Envíelo en formato JSON."}
POST arreglo                                               400  {"error":"El cuerpo debe ser un objeto JSON."}
A3.10 tomar ya asignado (de Diego)                         409  {"error":"Este cliente ya no está disponible."}
A3.10 tomar inexistente                                    404  {"error":"No se encontró el cliente."}
tomar uno propio                                           409  {"error":"Este cliente ya no está disponible."}
F2.5 tomar Gimnasio Fuerza                                 200  {"id":21,"nombre":"Gimnasio Fuerza","nit":"CF","telefono":"30002006","correo":null}
A3.7 Carlos crea (9 -> 10)                                 201  {"id":25,"nombre":"Kiosko Carlos","nit":"CF","telefono":"24445555","correo":null}
A3.7 Carlos toma otro                                      409  {"error":"Su cartera ya tiene 10 clientes, el máximo permitido."}
Carlos crea otro (lleno)                                   409  {"error":"Su cartera ya tiene 10 clientes, el máximo permitido."}
```

### A4 — Oportunidades, etapas y regla propia (API real, `curl.exe`)

La Gaseosa lata 355 ml (caja de 24) se creó con `Producto::create` (el mismo modelo que usa el
formulario del catálogo), porque la prueba RP1–RP4 se ejecutó antes de completar `SectorSeeder`. La
semilla sectorial actual también contiene productos con caja 24 y líneas con cantidades válidas. Los
leyeron con SQL antes y después de cada rechazo.

```
== Lecturas ==
sin token                                               401  {"error":"No autorizado."}
A4.18 GET /productos                                    200  [{"id":1,"nombre":"Plan básico","precio":1200},{"id":2,"nombre":"Plan avanzado","precio":2750.5},{"id":3,"nombre":"Instalación","precio":450},{"id":4,"nombre":"Soporte anual","precio":3600}]
GET /oportunidades (Valeria: 7)                         200  [{"id":1,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Renovación de planes","etapa":"ganada","probabilidad":100,"total":5650,"fecha_cierre":"2026-10-05T16…
   -> 7 oportunidades
GET ?etapa=ganada                                       200  [{"id":1,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Renovación de planes","etapa":"ganada","probabilidad":100,"total":5650,"fecha_cierre":"2026-10-05T16…
   -> 3: Renovación de planes, Equipo para sucursal, Soporte del hogar
A4.16 ?etapa=xyz                                        400  {"error":"El filtro etapa debe ser prospecto, calificado, propuesta, negociacion, ganada o perdida."}
?etapa=a&etapa=b                                        400  {"error":"El filtro etapa debe ser prospecto, calificado, propuesta, negociacion, ganada o perdida."}
GET /oportunidades/abc                                  400  {"error":"El identificador no es válido."}
detalle Renovación de planes                            200  {"id":1,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Renovación de planes","etapa":"ganada","probabilidad":100,"total":5650,"fecha_cierre":"2026-10-05T16:00:00.000Z","motivo_perdida":null,"productos":[{"id":1,"producto_id":2,"nombre":"Plan avanzado","cantidad":2,"precio_unitario":2600,"subtotal":5200},{"id":2,"producto_id":3,"nombre":"Instalación","cantidad":1,"precio_unitario":450,"subtotal":450}],"historial":[{"etapa_anterior":null,"etapa_nueva":"prospecto","usuario":"Valeria López","fecha":"2026-09-23T16:00:00.000Z"},{"etapa_anterior":"prospecto","etapa_nueva":"calificado","usuario":"Valeria López","fecha":"2026-09-26T16:00:00.000Z"},{"etapa_anterior":"calificado","etapa_nueva":"propuesta","usuario":"Valeria López","fecha":"2026-09-29T16:00:00.000Z"},{"etapa_anterior":"propuesta","etapa_nueva":"negociacion","usuario":"Valeria López","fecha":"2026-10-02T16:00:00.000Z"},{"etapa_anterior":"negociacion","etapa_nueva":"ganada","usuario":"Valeria López","fecha":"2026-10-05T16:00:00.000Z"}]}
A4.15 GET de Diego                                      404  {"error":"No se encontró la oportunidad."}
GET inexistente                                         404  {"error":"No se encontró la oportunidad."}
PATCH etapa de Diego, cuerpo inválido (404 antes)       404  {"error":"No se encontró la oportunidad."}
POST producto a la de Diego                             404  {"error":"No se encontró la oportunidad."}

== Crear (A4.1–A4.3) ==
A4.1 POST con cliente propio                            201  {"id":12,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Pedido completo","etapa":"prospecto","probabilidad":10,"total":0,"fecha_cierre":null,"motivo_perdida":null,"productos":[],"historial":[{"etapa_anterior":null,"etapa_nueva":"prospecto","usuario":"Valeria López","fecha":"2026-11-08T22:52:46.000Z"}]}
A4.2 cliente de Diego                                   422  {"errores":{"cliente_id":"Elija un cliente de su cartera."}}
A4.2 cliente disponible                                 422  {"errores":{"cliente_id":"Elija un cliente de su cartera."}}
cliente inexistente                                     422  {"errores":{"cliente_id":"Elija un cliente de su cartera."}}
POST {}                                                 422  {"errores":{"titulo":"Escriba el título.","cliente_id":"Elija un cliente de su cartera."}}
POST titulo con espacios                                422  {"errores":{"titulo":"Escriba el título."}}
A4.3 con etapa ganada y vendedor_id                     201  {"id":13,"cliente":{"id":3,"nombre":"María Coyoy"},"titulo":"Con campos ignorados","etapa":"prospecto","probabilidad":10,"total":0,"fecha_cierre":null,"motivo_perdida":nu…
A4.3 Diego no la ve                                     404  {"error":"No se encontró la oportunidad."}

== Productos de la oportunidad (A4.4–A4.8) ==
A4.4 Plan básico x3                                     201  {"id":12,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Pedido completo","etapa":"prospecto","probabilidad":10,"total":3600,"fecha_cierre":null,"motivo_perdida":…
   -> total 3600; línea precio 1200
A4.5 Plan básico otra vez                               409  {"error":"Este producto ya está en la oportunidad. Cambie su cantidad."}
A4.6 Licencia anterior (inactiva)                       422  {"errores":{"producto_id":"Elija un producto activo del catálogo."}}
A4.6 producto inexistente                               422  {"errores":{"producto_id":"Elija un producto activo del catálogo."}}
A4.7 Instalación con precio_unitario 1                  201  {"id":12,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Pedido completo","etapa":"prospecto","probabilidad":10,"total":4050,"fecha_cierre":null,"motivo_perdida":…
   -> precio guardado 450; total 4050
A4.8 PATCH cantidad 0                                   422  {"errores":{"cantidad":"La cantidad debe ser un número entero de 1 a 999."}}
A4.8 PATCH cantidad 1000                                422  {"errores":{"cantidad":"La cantidad debe ser un número entero de 1 a 999."}}
A4.8 PATCH cantidad 2.5                                 422  {"errores":{"cantidad":"La cantidad debe ser un número entero de 1 a 999."}}
A4.8 PATCH cantidad "3"                                 422  {"errores":{"cantidad":"La cantidad debe ser un número entero de 1 a 999."}}

== prospecto → ganada (A4.9) ==
a calificado                                            200  {"id":12,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Pedido completo","etapa":"calificado","probabilidad":25,"total":4050,"fecha_cierre":null,"motivo_perdida"…
   -> etapa calificado, probabilidad 25
a propuesta                                             200  {"id":12,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Pedido completo","etapa":"propuesta","probabilidad":50,"total":4050,"fecha_cierre":null,"motivo_perdida":…
   -> etapa propuesta, probabilidad 50
a negociacion                                           200  {"id":12,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Pedido completo","etapa":"negociacion","probabilidad":75,"total":4050,"fecha_cierre":null,"motivo_perdida…
   -> etapa negociacion, probabilidad 75
a ganada                                                200  {"id":12,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Pedido completo","etapa":"ganada","probabilidad":100,"total":4050,"fecha_cierre":"2026-11-08T22:52:46.000…
   -> etapa ganada, probabilidad 100, fecha_cierre 2026-11-08T22:52:46.000Z, motivo , total 4050
   -> historial:
                    -> prospecto   Valeria López  2026-11-08T22:52:46.000Z
        prospecto   -> calificado  Valeria López  2026-11-08T22:52:46.000Z
        calificado  -> propuesta   Valeria López  2026-11-08T22:52:46.000Z
        propuesta   -> negociacion Valeria López  2026-11-08T22:52:46.000Z
        negociacion -> ganada      Valeria López  2026-11-08T22:52:46.000Z

== Cerrada = 409 (A4.12) ==
título                                                  409  {"error":"La oportunidad está cerrada y ya no se puede modificar."}
etapa a perdida                                         409  {"error":"La oportunidad está cerrada y ya no se puede modificar."}
agregar producto                                        409  {"error":"La oportunidad está cerrada y ya no se puede modificar."}
cambiar cantidad                                        409  {"error":"La oportunidad está cerrada y ya no se puede modificar."}
quitar producto                                         409  {"error":"La oportunidad está cerrada y ya no se puede modificar."}
la nueva ganada: título                                 409  {"error":"La oportunidad está cerrada y ya no se puede modificar."}

== Transiciones que D1 no permite (A4.13) ==
Seguimiento anual -> propuesta                          409  {"error":"Ese cambio de etapa no está permitido."}
Seguimiento anual -> negociacion                        409  {"error":"Ese cambio de etapa no está permitido."}
Seguimiento anual -> ganada                             409  {"error":"Ese cambio de etapa no está permitido."}
Seguimiento anual -> prospecto                          409  {"error":"Ese cambio de etapa no está permitido."}
Capacitación de personal -> calificado                  409  {"error":"Ese cambio de etapa no está permitido."}
Capacitación de personal -> ganada                      409  {"error":"Ese cambio de etapa no está permitido."}
Capacitación de personal -> propuesta                   409  {"error":"Ese cambio de etapa no está permitido."}
Ampliación a bodega -> propuesta                        409  {"error":"Ese cambio de etapa no está permitido."}
Ampliación a bodega -> negociacion                      409  {"error":"Ese cambio de etapa no está permitido."}
   -> historial antes 49, después 49

== Ganar sin productos, perder, etapa inválida (A4.10, A4.11, A4.17) ==
nueva sin productos                                     201  {"id":14,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Sin productos","etapa":"prospecto","probabilidad":10,"total":0,"fecha_cierre":null,"motivo_perdida":…
a calificado                                            200  {"id":14,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Sin productos","etapa":"calificado","probabilidad":25,"total":0,"fecha_cierre":null,"motivo_perdida"…
a propuesta                                             200  {"id":14,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Sin productos","etapa":"propuesta","probabilidad":50,"total":0,"fecha_cierre":null,"motivo_perdida":…
a negociacion                                           200  {"id":14,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Sin productos","etapa":"negociacion","probabilidad":75,"total":0,"fecha_cierre":null,"motivo_perdida…
A4.10 ganar sin productos                               422  {"errores":{"etapa":"Agregue al menos un producto antes de ganar la oportunidad."}}
   -> historial antes 53, después 53
A4.11 perder sin motivo                                 422  {"errores":{"motivo_perdida":"Escriba el motivo de la pérdida."}}
A4.11 perder con solo espacios                          422  {"errores":{"motivo_perdida":"Escriba el motivo de la pérdida."}}
A4.17 etapa xyz                                         422  {"errores":{"etapa":"Elija una etapa: prospecto, calificado, propuesta, negociacion, ganada o perdida."}}
A4.17 sin etapa                                         422  {"errores":{"etapa":"Elija una etapa: prospecto, calificado, propuesta, negociacion, ganada o perdida."}}
perder con motivo                                       200  {"id":14,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Sin productos","etapa":"perdida","probabilidad":0,"total":0,"fecha_cierre":"2026-11-08T22:52:49.000Z…
   -> perdida, probabilidad 0, motivo 'Cerró el negocio', cierre 2026-11-08T22:52:49.000Z

== Título, cantidad y quitar (A4.19–A4.22) ==
A4.19 título nuevo                                      200  {"id":7,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Seguimiento anual 2027","etapa":"prospecto","probabilidad":10,"total":0,"fecha_cierre":null,"motivo_p…
   -> Seguimiento anual 2027
A4.19 título con espacios                               422  {"errores":{"titulo":"Escriba el título."}}
A4.22 PATCH con línea de Renovación                     404  {"error":"No se encontró el producto en esta oportunidad."}
A4.22 DELETE con línea de Renovación                    404  {"error":"No se encontró el producto en esta oportunidad."}
A4.20 Instalación a 3                                   200  {"id":5,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Capacitación de personal","etapa":"propuesta","probabilidad":50,"total":1350,"fecha_cierre":null,"motivo_p…
   -> subtotal 1350, total 1350
A4.21 DELETE la línea (204)                             204  
A4.21 detalle sin productos                             200  {"id":5,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Capacitación de personal","etapa":"propuesta","probabilidad":50,"total":0,"fecha_cierre":null,"motivo_perd…
   -> productos 0, total 0

== Regla propia: venta por caja (RP1–RP4) ==
nueva para la regla                                     201  {"id":15,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Gaseosas por caja","etapa":"prospecto","probabilidad":10,"total":0,"fecha_cierre":null,"motivo_perdi…
RP2 Gaseosa x30                                         422  {"errores":{"cantidad":"Este producto se vende por caja de 24 unidades: la cantidad debe ser múltiplo de 24."}}
RP1 Gaseosa x48                                         201  {"id":15,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Gaseosas por caja","etapa":"prospecto","probabilidad":10,"total":312,"fecha_cierre":null,"motivo_perdida":null,"productos":[{"id":17,"producto_id":6,"nombre":"Gaseosa lata 355 ml","cantidad":48,"precio_unitario":6.5,"subtotal":312}],"historial":[{"etapa_anterior":null,"etapa_nueva":"prospecto","usuario":"Valeria López","fecha":"2026-11-08T22:52:50.000Z"}]}
   -> copia en la línea: {"cantidad":48,"precio_unitario":"6.50","unidades_por_caja":24}
RP3 PATCH a 36                                          422  {"errores":{"cantidad":"Este producto se vende por caja de 24 unidades: la cantidad debe ser múltiplo de 24."}}
RP3 PATCH a 72                                          200  {"id":15,"cliente":{"id":1,"nombre":"Comercial El Quetzal"},"titulo":"Gaseosas por caja","etapa":"prospecto","probabilidad":10,"total":468,"fecha_cierre":null,"motivo_per…
   -> cantidad 72, subtotal 468
   (Alicia cambia unidades_por_caja de la Gaseosa a 12 en el catálogo)
RP4 PATCH a 60 (la línea conserva 24)                   422  {"errores":{"cantidad":"Este producto se vende por caja de 24 unidades: la cantidad debe ser múltiplo de 24."}}
otra oportunidad                                        201  {"id":16,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Gaseosas 2","etapa":"prospecto","probabilidad":10,"total":0,"fecha_cierre":null,"motivo_perdida":null,"pr…
RP4 línea nueva con 60 (copia 12)                       201  {"id":16,"cliente":{"id":2,"nombre":"Ferretería Xela"},"titulo":"Gaseosas 2","etapa":"prospecto","probabilidad":10,"total":390,"fecha_cierre":null,"motivo_perdida":null,"…
   -> cantidad 60
   -> Producto en la respuesta sin unidades_por_caja: {"id":6,"nombre":"Gaseosa lata 355 ml","precio":6.5}
```

### L5 y L6 — Cifras de control (servicio `ReporteMes` sobre `crm_agua`)

```
Empresa · 2026-10: ventas 4, total 22450.00, pronóstico 6751.13
    Carlos Ajú      1    7200.00   4125.75
    Diego Castillo   1    6000.00    112.50
    Iván Toj        0       0.00      0.00
    Valeria López   2    9250.00   2512.88
    producto Instalación   1 -> 450.00
    producto Plan avanzado  2 -> 5200.00
    producto Plan básico   5 -> 6000.00
    producto Soporte anual  3 -> 10800.00
Equipo de Sergio · 2026-10: ventas 3, total 15250.00, pronóstico 2625.38
    Diego Castillo   1    6000.00    112.50
    Valeria López   2    9250.00   2512.88
Equipo de Sandra · 2026-10: ventas 1, total 7200.00, pronóstico 4125.75
    Carlos Ajú      1    7200.00   4125.75
    Iván Toj        0       0.00      0.00
Empresa · 2026-11: ventas 1, total 3600.00, pronóstico 6751.13
    Carlos Ajú      0       0.00   4125.75
    Diego Castillo   0       0.00    112.50
    Iván Toj        0       0.00      0.00
    Valeria López   1    3600.00   2512.88
Mi equipo (Sergio) · Diego Castillo   cartera 2 de 10 · 1 ventas · 6000.00
Mi equipo (Sergio) · Valeria López   cartera 3 de 10 · 2 ventas · 9250.00
Base: crm_agua · MySQL 8.0.40
```

Coinciden con la sección 11 del enunciado y con la tabla de «Cifras de control» de L1.

### F2 — SPA (Edge sin ventana, 375 px, API real)

```
== F1: login ==
OK    375 px sin scroll horizontal (login)
OK    401 del login: mensaje del servidor, sin aviso de sesión vencida
OK    el correo escrito se conserva
OK    F2.1 cartera con 3 y «3 de 10»
OK    NIT null se muestra CF (María Coyoy)
OK    localStorage solo guarda el token
OK    375 px sin scroll horizontal (cartera)
== Búsqueda en el servidor con debounce ==
OK    ?q=Xela devuelve solo Ferretería Xela
OK    debounce: una sola petición al servidor para 4 teclas — ["http://127.0.0.1:3000/api/clientes?q=Xela"]
OK    el foco sigue en la búsqueda tras redibujar
OK    el contador sigue siendo la cartera completa
OK    A3.18 desde la SPA: 4521817-k encuentra Comercial El Quetzal
== F2.4 / F2.5: disponibles y tomar ==
OK    F2.4 seis disponibles, cada uno con «Tomar»
OK    375 px sin scroll horizontal (disponibles)
OK    F2.5 Gimnasio Fuerza sale de disponibles sin recargar
OK    F2.5 aparece en la cartera y el contador pasa a «4 de 10»
== F2.3: 409 al tomar uno que otro ya tomó ==
OK    Diego lo toma primero por la API
OK    F2.3 muestra el mensaje del 409 y refresca la lista (quedan 4)
== F2.2: alta con errores del 422 junto a cada campo ==
OK    mensaje del servidor junto al NIT
OK    mensaje junto al teléfono y al correo
OK    lo escrito se conserva
OK    375 px sin scroll horizontal (alta con errores)
OK    409 (NIT repetido) con el mensaje del servidor, datos conservados
== S7 / F6.4: nombre malicioso ==
OK    alta 201: se cierra el formulario y el contador pasa a «5 de 10»
OK    el nombre se ve como texto
OK    no se creó ningún <img> en la página
OK    no se ejecutó alert()
== Indicador de carga ==
OK    se ve el indicador mientras hay una petición en curso
== Recarga con token: se valida contra una ruta protegida ==
OK    recarga con token válido: entra directo a la cartera
OK    recarga con token alterado: vuelve al login con aviso
OK    el token alterado se borró
== 401 en plena sesión: usuario desactivado ==
OK    cuenta desactivada a media sesión: vuelve al login con aviso
== API apagada ==
   (API apagada)
OK    API apagada: mensaje que lo explica y botón «Reintentar»
OK    no es una pantalla vacía (la sesión sigue)

Consola del navegador: (sin errores)
Todo dio lo esperado.
```

### `node --test` (`npm test` en `api/`)

```
✔ NIT: mismos resultados que Nit.php (1.3206ms)
✔ Teléfono: mismos resultados que Telefono.php (0.2732ms)
✔ Correo: mismos resultados que la regla del panel (email + Correo.php) (0.987ms)
✔ Valores que no son texto son inválidos (no lanzan) (0.1004ms)
ℹ tests 4
ℹ suites 0
ℹ pass 4
ℹ fail 0
ℹ cancelled 0
ℹ skipped 0
ℹ todo 0
ℹ duration_ms 122.6715
```

### `php artisan test` (salida de `vendor/bin/phpunit --testdox`)

```
PHPUnit 12.5.43 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.27
Configuration: C:\Users\cuenta\Documents\6to Semestre\Progra Web\Proyecto Final\laravel\phpunit.xml

Time: 00:14.145, Memory: 72.00 MB

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
 ✔ Busqueda con filtros en forma de arreglo responde 400
 ✔ Busqueda toma porcentaje y guion bajo como texto
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

Equipos (Tests\Feature\Equipos)
 ✔ Los scopes definen lo que ve cada uno
 ✔ La policy responde 404 y no 403
 ✔ S5 sergio contra clientes de sandra recibe 404 antes de validar
 ✔ L5 4 sergio no ve a carlos ni a ivan ni sus clientes
 ✔ L5 2 sergio no asigna a carlos ni a ivan ni a quien no es vendedor
 ✔ Sandra no asigna a ivan desactivado pero ve su cartera
 ✔ L5 3 mi equipo con cartera y ventas del mes
 ✔ El administrador ve todo
 ✔ Un vendedor no entra a mi equipo

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

Reporte Mes (Tests\Feature\ReporteMes)
 ✔ L6 5 empresa por vendedor y por producto en octubre
 ✔ L6 6 supervisores ven solo su equipo
 ✔ L6 7 noviembre y un mes sin ventas da ceros
 ✔ El mes se agrupa en hora de guatemala y no en utc
 ✔ L3 5 el total usa el precio de la linea y no el del catalogo
 ✔ El mes actual y las fechas se calculan en hora de guatemala
 ✔ La pagina del reporte muestra las cifras y el mes actual por omision
 ✔ Un mes invalido se rechaza con mensaje y no con 500
 ✔ L6 1 l6 2 y l6 4 lista de oportunidades
 ✔ Filtros invalidos de la lista responden 400
 ✔ L6 3 el panel no modifica oportunidades y el vendedor no entra

OK (110 tests, 741 assertions)
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

### Cierre de la bitácora

Los cinco casos anteriores cumplen P5: cada uno identifica la propuesta incorrecta, la observación que
la descubrió, el commit que la introdujo, el commit corrector y la lección aplicable. No se agregan casos
sin evidencia de la historia real del repositorio.
