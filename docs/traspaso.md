# Traspaso del bloque 1 al bloque 2

> Del Integrante A al Integrante B · 2026-11-08 · último commit del bloque 1 al escribir esto: `9b50b18`.
> Fuentes: `CLAUDE.md`, `docs/especificacion.md` (spec-v1 + aclaración 1.1) y `docs/plan.md`.
> Aquí no hay contraseñas: cada quien usa las suyas en su `.env`.

## 1. Qué quedó hecho en el bloque 1

| Tarea del plan | Estado | Commits |
|----------------|--------|---------|
| 1.1 Cierre de spec | `spec-v1` en GitHub (`90613b0`). **Pendiente:** hacer push de `main` (ver el paso 6 de 1.1) | — |
| 1.2 Plan | Hecho | `1418c7e` |
| 1.3 Proyecto Laravel 13 + Breeze (Blade) + MySQL | Hecho | `553dbbc`, `ba05381`, `c143e03` |
| 1.4 Quitar el registro público | Hecho (`/register` → 404) | `7651aa2` |
| 1.5 Migraciones (9, con FK, UQ y `CHECK`; regla de unidades por caja en dos migraciones aparte) | Hecho | `bbdd0fa` |
| 1.6 `VerificacionSeeder` (sección 11 exacta) y `SectorSeeder` vacío | Hecho; cifras de control verificadas | `ca57890` |
| 1.7 L2: cuentas y roles | Hecho (50 pruebas en verde) | `2d95e98`, `4ab6931`, `cd155ec`, `bcb4ed0`, `0b4755a` |
| 1.8 Aclaración del teléfono en la especificación | Hecho, sin mover `spec-v1` | `62dbb32` |
| Evidencia de L1 y L2 | En `docs/verificacion.md`, sección «L1 y L2» | `9b50b18` |

Lo que ya existe en `laravel/` y le sirve para su bloque:

- **Modelos:** `User` (con `rol`, `supervisor_id`, `activo`, relaciones `supervisor`, `vendedores` y
  `clientes`, y `motivoParaNoCambiarRol()` para D6) y un `Cliente` mínimo, que usted amplía en L4.
- **Middleware:** `CuentaDelPanel`, en el grupo `web`, expulsa en la siguiente petición a una cuenta
  desactivada o vendedora. `rol:` (`SoloRol`) restringe rutas por rol: `->middleware('rol:administrador')`.
- **Cuentas:** `UsuarioController` + `GuardarUsuarioRequest` en `/usuarios` (solo administrador). Es
  el ejemplo a seguir para los FormRequest de L3 y L4: mensajes en `messages()`, datos que vuelven
  con `old()`, `{{ }}` en Blade.
- **Pruebas:** `UserFactory` crea un administrador por omisión, con estados `supervisor()` y
  `vendedor($supervisor)`.

Pendiente de decidir en L7 (anotado en `docs/plan.md`, tarea 3.6): las rutas de verificación de
correo y de recuperación de contraseña de Breeze, y la traducción del resto de vistas de Breeze.
Tampoco está la URL de la SPA en el mensaje para vendedores: hace falta una variable de
configuración nueva, que se decide cuando exista la SPA (F1).

## 2. Cómo dejar el entorno listo

Versiones con las que se probó el bloque 1: PHP 8.4.27, Composer 2.10, Node 24.21 (npm 11.19),
MySQL **8.0.40**. El enunciado pide MySQL 8.4 LTS: hay que repetir la verificación en 8.4 antes de la entrega.

1. **Extensiones de PHP.** En `php.ini` (`php --ini` dice cuál se carga) deben estar habilitadas:
   `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `bcmath`, `ctype`, `dom`, `xml`,
   `tokenizer`, `iconv`. Compruébelo con `php -m`. Sin `pdo_mysql` Laravel no se conecta a MySQL.
2. **Código:** `git pull` y, dentro de `laravel/`, `composer install` (`vendor/` no está en Git).
3. **Bases de datos** (en MySQL Workbench o en la consola, con su usuario):

   ```sql
   CREATE DATABASE crm_agua      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE crm_agua_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   `crm_agua` es la base de trabajo (panel y API). `crm_agua_test` es **solo** para `php artisan
   test`: `RefreshDatabase` la borra en cada corrida. Nunca apunte las pruebas a `crm_agua`.
4. **`.env`:** dentro de `laravel/`, `cp .env.example .env`; complete **solo** `DB_USERNAME` y
   `DB_PASSWORD` (ya trae `DB_CONNECTION=mysql`, `DB_DATABASE=crm_agua` y `APP_TIMEZONE=UTC`); luego
   `php artisan key:generate`. `.env` nunca se sube (ya está en `.gitignore`). `phpunit.xml` toma
   el usuario y la contraseña de ese mismo `.env` y fuerza `DB_DATABASE=crm_agua_test`.
5. **Estilos:** `npm install` y `npm run build` dentro de `laravel/` (Breeze compila Tailwind).
6. **Semilla:** `php artisan migrate:fresh --seeder=VerificacionSeeder`. Debe dar 7 cuentas,
   5 productos, 21 clientes, 11 oportunidades, 43 registros de historial y 4 actividades.
   La contraseña de las cuentas es la pública de las semillas (sección 11 del enunciado; también
   está en el README).
7. **Pruebas:** `php artisan test` → deben pasar las 50.
8. **Panel:** `php artisan serve` → `http://localhost:8000`. Alicia (`admin@crm.test`) entra y ve
   «Cuentas»; Valeria (`valeria@crm.test`) no entra y ve el mensaje que la envía a la SPA.

## 3. Decisiones que condicionan el bloque 2

- **Teléfono (spec 3.4, aclaración 1.1).** Se quitan espacios, guiones, puntos y paréntesis; si
  empieza con `+502` se quita ese prefijo; si no, y quedan **exactamente 11 dígitos** que empiezan
  con `502`, se quita el prefijo; el resultado debe cumplir `^[2-9][0-9]{7}$`.
  `5021 2345` → `50212345` (válido); `502 5512 3456` → `55123456`.
  La lista completa de casos está en la sección 5 de `docs/plan.md`.
- **NIT y teléfono se normalizan igual en Laravel y en la API (R4, L7.2).** En la tarea 2.5 usted crea
  `docs/casos-normalizacion.json` con los casos del plan y una prueba de Laravel que los recorra; en
  la 3.3, la API usa el mismo archivo con `node:test`. Si una puerta acepta lo que la otra rechaza, es un defecto.
- **Mensajes en español y de usted**, en el panel, la API y la SPA: «Escriba un teléfono de Guatemala
  de 8 dígitos.». En Laravel van en `messages()` del FormRequest (`APP_LOCALE` sigue en `en`; no hace falta cambiarlo).
- **Fechas en UTC.** La base guarda UTC: `APP_TIMEZONE=UTC` y la conexión `mysql` con
  `'timezone' => '+00:00'`. Los meses se agrupan en hora de Guatemala con
  `fecha_cierre - INTERVAL 6 HOUR`. Se muestran en hora de Guatemala, sin depender de la zona de la computadora.
- **API con mysql2:** el pool lleva `timezone: 'Z'` y `decimalNumbers: true`, para que el DECIMAL
  llegue como **número** (`9250`, no `"9250.00"`). Los totales los calcula SQL con el precio guardado en la línea.
- **API, acceso (A1, A2):** `bcryptjs` (no `bcrypt`) para comparar los hashes `$2y$` de Laravel; el
  login responde 403 a administradores, supervisores y cuentas desactivadas; el middleware vuelve a
  leer al usuario en cada petición (activo y vendedor), si no, 401.
- **Cartera máxima (L4, R3):** al asignar, bloquee la fila del **vendedor** en `users`
  (`lockForUpdate()`) antes de contar sus clientes. Es la misma fila que bloquea el cambio de rol (D6)
  en `UsuarioController::update`, y la que bloqueará la API al tomar o crear clientes.
- **Reasignar (D2) y liberar (D3):** la reasignación mueve **solo** las oportunidades abiertas y
  **no toca** `actividades`; liberar se rechaza si hay abiertas, con el mensaje del panel de D3.
- **Catálogo (L3):** el precio con más de 2 decimales lo rechaza **solo** la validación de Laravel
  (MySQL lo redondearía sin avisar); `unidades_por_caja` vacío o entero de 1 a 999.

## 4. Qué empieza primero

En el orden de la tabla del bloque 2 de `docs/plan.md`:

1. **2.1 Base de la API** (`api/`): Express 5, pool mysql2, CORS con `CORS_ORIGIN`, errores JSON.
2. **2.2 A1 Login JWT** y **2.3 A2 Middleware**: con la semilla, Valeria entra (200) y Alicia,
   Sergio e Iván reciben 403.
3. **2.4 L3 Catálogo** en el panel.
4. **2.5 L4 Clientes y asignación**, con `docs/casos-normalizacion.json`.
5. **2.6 F1 SPA: sesión**, contra la API real desde `http://127.0.0.1:5500` (primer hito de
   integración, I1).

Al terminar, el traspaso 2 → 3 lo hace el Integrante A: corre las pruebas de su bloque y lee el diff completo.

---

# Traspaso del bloque 2 al bloque 3

> Del Integrante B al Integrante A · 2026-11-08 · rama `bloque-3/equipos-reportes-cartera`.
> Fuentes: `CLAUDE.md`, `docs/especificacion.md` (spec-v1 + aclaraciones 1.1 y 1.2) y `docs/plan.md`.
> La evidencia completa está en `docs/verificacion.md`, sección «Bloque 2». Aquí no hay contraseñas.

## 1. Qué quedó hecho en el bloque 2

| Tarea del plan | Estado | Commits |
|----------------|--------|---------|
| 2.1 Base de la API (Express 5, pool mysql2, CORS, errores JSON) | Hecho | `ab90dd6` |
| 2.2 A1 Login JWT | Hecho; endurecido: sin cuerpo da 400, hash ficticio contra la enumeración por tiempo, `JWT_SECRET` ≥ 32 al arrancar | `3eddf1d`, `be50f6e` |
| 2.3 A2 Middleware | Hecho. El middleware se escribió en `3eddf1d` **sin montarse**; ahora cubre todo `/api` salvo `POST /api/auth/login` | `3eddf1d`, `bfcdee2` |
| 2.4 L3 Catálogo (con `unidades_por_caja`) | Hecho; precio máximo y un mensaje por campo | `b5da01f`, `1ddf305` |
| 2.5 L4 Clientes y asignación, `Support/Nit.php`, `Support/Telefono.php`, `docs/casos-normalizacion.json` | Hecho. Correcciones: autorizar antes de validar (404), solo el administrador elimina, nombre obligatorio al editar, `lang/es`, mismo orden de bloqueos | `f63434a`, `241d490`, `62b4f31`, `1fff582`, `ae9afaa` |
| 2.6 F1 SPA: sesión | Hecho; F1.4 comprobado contra la API real | `f897d78` |
| Aclaraciones de la spec (versión 1.2, `spec-v1` no se mueve) | Login sin cuerpo = 400 y `{}` = 422; alcance del middleware; L4.18 | `563e6ce` |
| Bitácora (casos 2–5) y evidencia del bloque 2 | Hecho | `9264b99`, `c0fdd7b` |

`php artisan test`: **88 pruebas, 460 aserciones, todas en verde** (eran 50 al cerrar el bloque 1).

**Sobre los commits:** el bloque 2 llegó primero en un commit local, `e3273d5` («Implementa catálogo,
clientes y acceso API»), **sin prefijo y con todo el bloque en uno solo**. Se descartó al reiniciar la
rama a `origin/feat/bloque-2-api-base`, y hoy no está en ninguna rama (solo en el reflog local). El
mismo código está en `ab90dd6`…`f897d78`, con prefijo. `f897d78` usa `frontend:` en vez de `spa:`
(plan, sección 4). Como no se reescribe el historial, queda así y se usa `spa:` de aquí en adelante.

## 2. Cómo dejar el entorno listo (además de lo del traspaso 1 → 2)

Versiones con las que se probó el bloque 2: PHP 8.4.27, Node 24.21, **MySQL 8.0.40 en lugar de la
8.4 LTS** que pide el enunciado. Hay que repetir las pruebas y las cifras de control en MySQL 8.4
antes de la entrega.

1. **`laravel/.env`:** cambie `APP_LOCALE=en` por `APP_LOCALE=es` (ya está así en `.env.example`).
   Sin eso, el panel muestra en inglés los mensajes de respaldo y la paginación. Las pruebas no
   dependen de esto: `phpunit.xml` fija `APP_LOCALE=es`.
2. **`api/.env` no existe en el repositorio** (y nunca debe entrar). Créelo desde el ejemplo:
   `cd api`, `cp .env.example .env`, y complete `DB_USERNAME`, `DB_PASSWORD` y un **`JWT_SECRET` de al
   menos 32 caracteres aleatorios** (por ejemplo, la salida de
   `node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"`). Si falta o es corto, la
   API no arranca y lo dice. Luego `npm install` y `npm start` (`node --env-file=.env`).
3. **CORS:** `CORS_ORIGIN` debe coincidir **exactamente** con el origen de la SPA:
   `http://127.0.0.1:5500`, **no** `http://localhost:5500`. Abra la SPA con Live Server desde
   `127.0.0.1:5500`. Desde `localhost:5500` la preflight no recibe `Access-Control-Allow-Origin` y el
   navegador bloquea la petición, aunque todo lo demás parezca bien (comprobado; ver `verificacion.md`, S6).
4. **Semilla y pruebas:** `php artisan migrate:fresh --seeder=VerificacionSeeder` y `php artisan test`
   (88 en verde).

## 3. Lo que el bloque 3 debe respetar

- **Nuevas rutas de la API:** se montan en `api/src/app.js` **después** de
  `app.use('/api', autenticacion)` y **antes** de `notFoundHandler`, para que hereden el 401. El dueño
  sale de `request.usuario.id`, nunca del cuerpo.
- **Cuerpos:** use `exigirCuerpo` (`api/src/middleware/cuerpo.js`) en los `POST`/`PATCH` que exigen
  cuerpo (sin cuerpo, 400). `PATCH /clientes/:id` con `{}` también es 400 (P-7), y eso lo decide la ruta.
- **Orden de bloqueos:** primero la fila del vendedor en `users` (`SELECT … FOR UPDATE`), luego la del
  cliente. Es el mismo orden que usa el panel en `asignar` y `liberar` desde `ae9afaa`. Con otro orden,
  una toma por la API y un liberar en el panel pueden bloquearse mutuamente.
- **NIT y teléfono en la API (3.3):** deben dar lo mismo que `laravel/app/Support/Nit.php` y
  `Telefono.php`. Ojo: `Nit::normalizar()` devuelve `null` tanto para `CF` como para un NIT inválido,
  así que primero se llama a `esValido()`. **`docs/casos-normalizacion.json` solo tiene 5 casos de
  NIT:** antes de la prueba `node:test`, agregue ` c f `, `0004521817K`, `1`, 13 caracteres y `k`
  minúscula.
- **Visibilidad en el panel (L5):** use `Cliente::esVisiblePara($usuario)`. En un FormRequest sobre un
  registro, la autorización va en `authorize()`, que corre antes de validar (bitácora, caso 3).

## 4. Pendientes que se encontraron (no son de este bloque)

- **`GET /clientes?q[]=x` y `?vendedor_id[]=4` responden 500** en el panel (comprobado el 2026-11-08).
  `ClienteController::index` convierte a texto un arreglo. Corresponde a L7 (tarea 3.6).
- **SPA:** tras un error de login el formulario se vuelve a dibujar y se pierde el correo escrito;
  hay un `<main>` dentro de otro `<main>`; al recargar con un token guardado no se valida hasta la
  primera petición protegida. Revisar en F2 o F6.
- **Decisiones pendientes de 3.6** (del traspaso 1 → 2): verificación de correo, recuperación de
  contraseña y el resto de vistas de Breeze en inglés.

---

# Traspaso del bloque 3 al bloque 4

> Del Integrante A al Integrante B · 2026-11-08 · rama `bloque-3/equipos-reportes-cartera`, último
> commit de código `6a0b42a`. La rama va **20 commits por delante de `origin`**: falta el `push`.
> Fuentes: `CLAUDE.md`, `docs/especificacion.md` (spec-v1 + aclaraciones 1.1 y 1.2) y `docs/plan.md`.
> La evidencia completa está en `docs/verificacion.md`, sección «Bloque 3». Aquí no hay contraseñas.

## 1. Qué quedó hecho en el bloque 3

| Tarea del plan | Estado | Commits |
|----------------|--------|---------|
| 3.1 L5 Equipos y visibilidad | Hecho: scopes `Cliente::visiblesPara`, `User::equipoDe` y `User::asignablesPor`; `ClientePolicy::gestionar` (404 con `denyAsNotFound`) aplicada con `can:` en las rutas, antes de validar; página «Mi equipo» | `510138c` |
| 3.2 L6 Oportunidades (solo lectura) y reporte del mes | Hecho: servicio `App\Services\ReporteMes`, `ZonaGuatemala` (rango UTC del mes, sin `CONVERT_TZ`), `Dinero`; las cifras de control coinciden | `c73747a` (un solo commit, no los dos del plan) |
| 3.3 A3 Clientes y cartera | Hecho: los 6 endpoints, `api/src/normalizar.js` con `node:test` sobre `docs/casos-normalizacion.json` | `7dbbf5f`, `e3d89a5` |
| 3.4 `ConcurrenciaSeeder` y script de concurrencia | **Parcial.** Hecho `api/scripts/concurrencia.mjs` (tomas, cartera llena, altas con el mismo teléfono y dos `PATCH /etapa`). **Falta `ConcurrenciaSeeder`**, y con él A3.8 tal como lo pide la spec; tampoco se probaron A3.9 ni dos altas con el mismo NIT | `e3d89a5`, `c7869d3` |
| 3.5 A4 Oportunidades, líneas, etapas y regla propia | Hecho, incluido `GET /productos` (A4.18) | `c7869d3` (un solo commit, no los dos del plan) |
| 3.6 L7 Validación | **Parcial.** Paridad de NIT, teléfono y correo en las dos puertas (mismo JSON de casos); búsqueda del panel con `%` y `_` como texto y 400 a `?q[]=`; vista 400 en español. **Falta** la tabla cruzada de L7.2 y decidir las vistas de Breeze (ver sección 4) | `fe36cd2`, `c9e31bc` |
| 3.7 F2 Cartera y disponibles | Hecho: F2.1–F2.5 en Edge sin ventana a 375 px contra la API real. **Falta** el hito I2 (un cliente asignado en el panel aparece en la SPA) | `6a0b42a` |
| Evidencia del bloque 3 | Hecho | `9a4f4c2` |

Pruebas al cerrar: `php artisan test` **110 pruebas, 741 aserciones, todas en verde**
(eran 88); `npm test` en `api/`: **4 en verde**; `node scripts/concurrencia.mjs`: los cuatro casos
dan lo esperado (1/4, 1/9, 1/4, 1/1 y 2 registros de historial).

**Los commits que el plan pedía separados** (3.2 y 3.5) quedaron en uno cada uno. El script se llama
`concurrencia.mjs` y no `concurrencia.js` como dice el plan (se pidió así).

## 2. Cómo dejar la base como la semilla

Varias pruebas cambian la base (el script de concurrencia toma clientes y crea un cliente y una
oportunidad; las pruebas de la regla propia crean la «Gaseosa lata 355 ml»). Para volver al estado
de la sección 11:

```
cd laravel
php artisan migrate:fresh --seeder=VerificacionSeeder
```

Debe dar 7 cuentas, 5 productos, 21 clientes, 11 oportunidades, 43 registros de historial y
4 actividades. `php artisan test` no toca `crm_agua`: usa `crm_agua_test`.

Para el script de concurrencia, con la API en marcha y la semilla recién cargada (desde `api/`, en
PowerShell): `$env:CLAVE_SEMILLA = '<contraseña del README>'; node scripts/concurrencia.mjs`. La
contraseña no está escrita en el script (CLAUDE.md).

**`api/.env` no existe en esta computadora.** Todo el bloque 3 se probó con las variables en el
entorno del proceso. Para `npm start`, créelo desde `api/.env.example` (traspaso 2 → 3, paso 2).

## 3. Qué hay listo para el bloque 4

### API (`api/src`)

| Endpoint | Estado | Para |
|----------|--------|------|
| `POST /auth/login` | Listo (bloque 2) | F1 |
| `GET /productos` | Listo | F3 (agregar productos) |
| `GET /clientes`, `POST /clientes`, `GET /clientes/disponibles`, `POST /clientes/:id/tomar`, `GET /clientes/:id`, `PATCH /clientes/:id` | Listos | F2 (hecho); F4 (elegir cliente) |
| `GET /oportunidades?etapa=`, `POST /oportunidades`, `GET /oportunidades/:id`, `PATCH /oportunidades/:id`, `PATCH /oportunidades/:id/etapa`, `POST/PATCH/DELETE /oportunidades/:id/productos[/:lineaId]` | Listos | F3 |
| `POST /clientes/:id/actividades`, `GET /actividades`, `PATCH /actividades/:id` | **Falta (A5)** | F4 |
| `GET /reportes/resumen?mes=` | **Falta (A6)** | F5 |

Piezas para reutilizar en A5 y A6:

- `db.js`: `transaccion(trabajo)` (commit, rollback y `release()` en `finally`).
- `middleware/cuerpo.js`: `exigirCuerpo` (400 sin cuerpo).
- `router.param` para `:id` numérico → 400 (como en `clientes.js` y `oportunidades.js`).
- Patrón 404 → 422 → (bloqueo) 409 → 422 de estado, comentado al inicio de `routes/oportunidades.js`.
- **A6 debe dar lo mismo que el panel.** `laravel/app/Services/ReporteMes.php` es la referencia: mes
  de Guatemala como rango UTC `[día 1 06:00, día 1 del mes siguiente 06:00)`, total con el precio de
  la línea, pronóstico `ROUND(SUM(total × probabilidad / 100), 2)` en la base sobre las abiertas, sin
  filtro de mes. Con la semilla: Valeria octubre 2 · 9250 · 2512.88 · 3 de 10.

### SPA (`frontend/js`)

- `util/dom.js`: `h()` crea elementos solo con nodos de texto (S7). Úselo en todas las vistas nuevas;
  no hay `innerHTML` en el código.
- `estado.js`: estado único; `actualizar(receta, { render })`. Al escribir en un campo, guarde el
  valor con `{ render: false }` para no perder el cursor.
- `api.js`: agregue aquí las funciones de A5 y A6. Un 401 **con token** vuelve al login con aviso; el
  del login no.
- `main.js`: router por hash (`#/cartera`, `#/disponibles`), delegación de eventos con `data-action` y
  `data-form`, y descarte de respuestas de una sesión vieja (`sesion`).
- `config.js`: URL de la API y origen, que deben coincidir con `PORT` y `CORS_ORIGIN` de `api/.env`.
- Al abrir la SPA con un token guardado se valida con `GET /clientes` (no hay ruta «yo» en la spec).

### Panel

- `ReporteMes::resumenPorVendedor()` y `equipo()` sirven para cruzar A6 y F5 con el panel.
- `MesRequest` valida `?mes=` (AAAA-MM; inválido vuelve con mensaje, nunca 500).

## 4. Riesgos y pendientes

- **MySQL 8.0.40 en lugar de 8.4 LTS.** Todo el bloque 3 se probó en 8.0.40 (confirmado con
  `SELECT VERSION()`). Antes de la entrega hay que repetir en 8.4: `migrate:fresh --seeder=VerificacionSeeder`,
  `php artisan test`, `npm test`, el script de concurrencia, las cifras de control y RP5. Puntos a
  mirar en 8.4: el plugin de autenticación por omisión (`caching_sha2_password`; mysql2 y PDO lo
  soportan, pero conviene comprobar que la API conecta), los `CHECK` con `REGEXP_LIKE`, `FOR SHARE`
  (A4) y los códigos de error 1062 y 3819 que traducen la API y el panel.
- **`ConcurrenciaSeeder` no existe** (tarea 3.4). Sin ella no se puede probar A3.8 como lo escribe la
  spec. Tampoco se probaron A3.9 ni dos altas simultáneas con el mismo NIT (S8).
- **Hito I2 sin probar:** asignar un cliente en el panel y verlo en la SPA.
- **L7.2 sin tabla cruzada.** NIT, teléfono y correo son idénticos por construcción (mismo JSON de
  casos), pero falta enviar los mismos datos de cliente a las dos puertas y anotar el resultado.
- **L7.1 y vistas de Breeze:** siguen pendientes las decisiones de la tarea 3.6 (verificación de
  correo, recuperación de contraseña y vistas en inglés). Las páginas 403 y 404 del panel siguen en
  inglés; la 400 ya está en español.
- **Vocabulario del panel:** las pantallas nuevas dicen «Pedidos por cerrar» y «Punto de venta»
  (spec 1.2), pero las de clientes siguen diciendo «Clientes».
- **`SectorSeeder` sigue vacío**: RP1–RP4 se probaron con un producto creado a mano (caja de 24).
- **Decisiones de implementación que la spec no fija** (conviene confirmarlas o escribirlas en la spec):
  orden de respuestas en A4 (404 → 422 de formato → 409 → 422 de estado); tomar un cliente propio da
  409; mensajes sin texto en la spec (etapa inválida, `cliente_id`, `producto_id`, oportunidad
  cerrada, transición no permitida); en el panel, un filtro con forma de arreglo y una etapa
  inexistente en la lista de pedidos dan 400; un mes inválido vuelve con mensaje y acepta años
  2000–2099.
- **Bitácora de IA:** no se agregaron casos del bloque 3; quedan candidatos para que la pareja decida
  si se registran.
