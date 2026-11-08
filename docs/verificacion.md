# Verificación

## Matriz de criterios y evidencia (P3)

| Criterio | Evidencia | Resultado |
|----------|-----------|-----------|

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
