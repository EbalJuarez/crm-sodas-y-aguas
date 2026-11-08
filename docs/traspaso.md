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
