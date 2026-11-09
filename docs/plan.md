# Plan

> P2. Arquitectura, estructura, fases y tareas con responsable, y qué se le pedirá al agente en cada fase.
> Se actualiza antes de cada fase.
>
> Base: `docs/especificacion.md` (etiqueta `spec-v1`, commit `90613b0`) y `CLAUDE.md`. Si este plan
> choca con la especificación, gana la especificación y se corrige el plan.

**Integrante A:** _(nombre por rellenar)_ · **Integrante B:** _(nombre por rellenar)_

| Bloque | Responsable | Inicio | Fin |
|--------|-------------|--------|-----|
| 1 | Integrante A | _(fecha)_ | _(fecha)_ |
| 2 | Integrante B | _(fecha)_ | 2026-11-08 · **hecho** |
| 3 | Integrante A | _(fecha)_ | 2026-11-08 · **hecho** (con pendientes) |
| 4 | Integrante B | _(fecha)_ | _(fecha)_ |
| Cierre | A y B | _(fecha)_ | _(fecha)_ |
| Entrega (`entrega-final`) | A y B | — | _(fecha: semana anterior al examen final)_ |

---

## 1. Arquitectura

```
                         ┌──────────────────────────────┐
  Administrador ───────▶ │  Panel Laravel 13 (Blade)    │  :8000
  Supervisor     sesión  │  Breeze · Eloquent           │
                 + CSRF  │  ÚNICO dueño del esquema     │──── migraciones (DDL) ───┐
                         │  ÚNICO creador de cuentas    │──── Hash::make ($2y$) ───┤
                         └──────────────────────────────┘                          ▼
                                                                         ┌──────────────────┐
                                                                         │  MySQL 8.4       │ :3306
                                                                         │  base crm_agua   │
                                                                         │  FK · UQ · CHECK │
                                                                         │  fechas en UTC   │
                                                                         └──────────────────┘
                                                                                   ▲
                         ┌──────────────────────────────┐                          │
  Vendedor ────────────▶ │  SPA (HTML + Bootstrap 5.3   │   fetch + JSON           │
  (navegador)            │  + módulos JS, sin framework)│ ─────────────┐           │
                         │  Live Server :5500           │  Bearer JWT  │           │
                         │  localStorage = solo token   │              ▼           │
                         └──────────────────────────────┘   ┌──────────────────────┴──┐
                                                            │  API Express 5 :3000     │
                                                            │  mysql2 (SQL a mano, ?)  │
                                                            │  jsonwebtoken HS256      │
                                                            │  bcryptjs.compare        │
                                                            │  cors (origen exacto)    │
                                                            │  sin DDL, sin ORM        │
                                                            └──────────────────────────┘
```

**Cómo se conectan**

| Unión | Cómo | Configuración que debe coincidir |
|-------|------|----------------------------------|
| Laravel → MySQL | Eloquent; las migraciones crean y modifican todas las tablas, `CHECK` incluidos | `laravel/.env`: `DB_CONNECTION=mysql`, `DB_DATABASE=crm_agua`, `APP_TIMEZONE=UTC`; `config/database.php` con `'timezone' => '+00:00'` |
| API → MySQL | Pool de `mysql2/promise`; solo `SELECT`/`INSERT`/`UPDATE`/`DELETE` con `?` | `api/.env`: los mismos `DB_*`; pool con `decimalNumbers: true` y `timezone: 'Z'` |
| SPA → API | `fetch` con `Authorization: Bearer <token>`, cuerpo y respuesta JSON | `CORS_ORIGIN` de `api/.env` = origen exacto de Live Server (`http://127.0.0.1:5500`); la URL base de la API, en un solo módulo de la SPA |
| Panel ↔ API | **Nunca** se llaman entre sí. Se comunican solo a través de la base: un cliente que asigna el panel aparece en la API; una venta que gana la API aparece en el reporte del panel | Mismas reglas R3/R4 en las dos (sección 5 de este plan) |
| Cuentas | Laravel genera el hash bcrypt (`$2y$`); la API solo lo compara con `bcryptjs` y relee al usuario en cada petición | — |

Node 24 lee el `.env` con `node --env-file=.env` (sin paquete `dotenv`). Las dependencias de la API
son solo las del enunciado: `express`, `mysql2`, `jsonwebtoken`, `bcryptjs`, `cors`.

---

## 2. Estructura de carpetas

```
crm-sodas-y-aguas/
├── README.md                     pasos para levantar las tres piezas y tabla de endpoints (A8)
├── CLAUDE.md                     reglas del agente (P4)
├── .gitignore                    .env, vendor/, node_modules/
├── docs/
│   ├── especificacion.md         P1 (spec-v1)
│   ├── plan.md                   P2 (este archivo)
│   ├── verificacion.md           P3 + bitácora de IA (P5)
│   ├── api.postman_collection.json
│   └── casos-normalizacion.json  casos de NIT y teléfono que prueban panel y API (sección 5)
├── laravel/                      el panel (sin vendor/ ni node_modules/)
│   ├── app/
│   │   ├── Http/Controllers/     Usuarios, Productos, Clientes, Equipo, Oportunidades, Reportes
│   │   ├── Http/Requests/        un FormRequest por formulario (mensajes en español, de usted)
│   │   ├── Http/Middleware/      RolPanel (solo administrador/supervisor), CuentaActiva
│   │   ├── Policies/             visibilidad por equipo (404 en lo ajeno)
│   │   ├── Models/               User, Producto, Cliente, Oportunidad, OportunidadProducto, HistorialEtapa, Actividad
│   │   └── Support/              Nit.php, Telefono.php, ZonaGuatemala.php
│   ├── database/migrations/      users (extensión), tablas del núcleo, columnas de la regla propia
│   ├── database/seeders/         DatabaseSeeder, VerificacionSeeder, SectorSeeder, ConcurrenciaSeeder
│   ├── resources/views/          Blade (nunca {!! !!} con datos del usuario)
│   └── .env.example
├── api/
│   ├── package.json
│   ├── .env.example
│   ├── scripts/concurrencia.js   peticiones simultáneas para A3.8, A3.9, A4.14 y S8 (fetch + Promise.all)
│   └── src/
│       ├── server.js             arranque (puerto)
│       ├── app.js                Express, cors, JSON, rutas, 404 y errores
│       ├── db.js                 pool mysql2 y ayudante de transacción
│       ├── middleware/           autenticacion.js (JWT + relectura del usuario), errores.js
│       ├── validacion/           nit.js, telefono.js, fechas.js, campos.js (listas blancas, D5)
│       ├── rutas/                auth, productos, clientes, actividades, oportunidades, reportes
│       └── servicios/            SQL de cada recurso (consultas escritas a mano)
└── frontend/
    ├── index.html
    ├── css/app.css
    └── js/
        ├── main.js               arranque, router por hash, delegación de eventos
        ├── estado.js             estado único de la aplicación
        ├── api.js                fetch, token, manejo de 401 y de API apagada
        ├── vistas/               login, cartera, disponibles, embudo, detalle, actividades, resumen
        └── util/                 escape.js, fechas.js (hora de Guatemala), dinero.js (quetzales)
```

---

## 3. Orden de fases

| Fase | Requisitos | Depende de | Pieza |
|------|------------|------------|-------|
| 0 | Cierre de la especificación (`spec-v1`) y este plan | — | docs |
| 1 | L1–L2: esquema, semillas, cuentas y roles | 0 | Laravel |
| 2 | A1–A2: login JWT y middleware | 1 (tablas y cuentas con hash) | API |
| 3 | L3–L7: catálogo, clientes, equipos, reportes, validación | 1 | Laravel |
| 4 | A3–A7: clientes, oportunidades, actividades, resumen, errores | 2 y 3 (L4 y L6 definen las reglas que la API debe igualar) | API |
| 5 | F1–F6: SPA | cada vista, cuando su endpoint existe | SPA |
| 6 | `SectorSeeder` | 1, 3 y 4 (necesita la regla propia completa) | Laravel |
| 7 | Verificación: matriz, seguridad, cifras, README, bitácora, `entrega-final` | todo | docs |

Las fases no se hacen «una pieza completa y luego la otra». Los bloques de la sección 4 intercalan
la SPA en cuanto existe el endpoint que consume (F1 en el bloque 2, F2 en el 3), para que la
integración no quede para el final (sección 6).

---

## 4. Bloques, tareas y traspasos

**Cómo se trabaja cada tarea**

1. Antes de empezar el bloque se releen `docs/especificacion.md` y este plan.
2. Cada prompt al agente empieza con este encabezado, aquí abreviado como **[E]**:
   > Lee CLAUDE.md, docs/especificacion.md y docs/plan.md. Haz solo la tarea indicada; si algo choca
   > con la especificación o no está decidido, detente y pregunta. No agregues dependencias ni hagas
   > commits. Al terminar, dime qué criterios de aceptación cumple y cómo comprobarlo.
3. El integrante del bloque revisa el diff, comprueba los criterios a mano o en Postman y hace el commit.
   Si el agente propuso algo incorrecto o distinto de la especificación, se anota en la bitácora (P5)
   con el commit que lo corrigió.
4. Prefijos de commit: `laravel:`, `api:`, `spa:`, `docs:`, `spec:`.

### Bloque 1 — Integrante A: cierre de spec, plan, setup de Laravel, L1, L2

| # | Requisito | Tarea | Terminado cuando | Prompt al agente | Commits |
|---|-----------|-------|------------------|------------------|---------|
| 1.1 | P1, S2 | Cierre de la especificación y punto de partida limpio (pasos 1–6 abajo) | Se cumplen los seis pasos: `spec-v1` en GitHub, `laravel/` antigua borrada, proyecto nuevo creado (1.3), ningún `.env` en Git ni en su historial, y `main` en GitHub con el proyecto base | — (lo hace la pareja; el agente solo puede dar los comandos) | — (el commit del proyecto base es el de 1.3) |
| 1.2 | P2 | Este plan | La pareja lo revisó y lo aprobó | «Escribe docs/plan.md con …» (este) | `docs: plan de implementacion` |
| 1.3 | L1, L2 | Setup de Laravel (paso 3 de 1.1): proyecto nuevo con Breeze y MySQL | `composer create-project laravel/laravel laravel` (Laravel 13); `composer require laravel/breeze --dev`; `php artisan breeze:install blade`; `npm install` y `npm run build`; `.env` con `DB_CONNECTION=mysql`, `DB_DATABASE=crm_agua` y `APP_TIMEZONE=UTC`; `php artisan about` muestra `mysql`; `laravel/.env.example` sin valores reales | [E] «Guíame para crear el proyecto Laravel 13 en `laravel/` con Breeze (Blade) y MySQL según la sección 1 del plan. Dime los comandos; no ejecutes nada destructivo sin preguntar.» | `laravel: proyecto base con Breeze (Blade)` |
| 1.4 | L2.1 | Quitar el registro público | `/register` responde 404 y el login no tiene enlace de registro (L2.1) | [E] «Elimina la ruta, el controlador y la vista de registro de Breeze, y el enlace del login.» | `laravel: elimina registro publico` |
| 1.5 | L1.1–L1.3, RP5 | Migraciones: extensión de `users`, las 6 tablas del núcleo y, en migraciones aparte, `unidades_por_caja` en `productos` y en `oportunidad_productos` | `migrate:fresh` sin errores; `SHOW CREATE TABLE` muestra cada FK, UQ y `CHECK` de la sección 4 de la spec; los inserts de L1.3 y RP5 fallan | [E] «Escribe las migraciones de la sección 4 de la especificación, una por tabla, con FK, índices únicos y CHECK (con `DB::statement` y SQL fijo, sin datos del usuario). `users` se extiende con una migración propia. Las columnas de la regla propia van en dos migraciones nuevas.» | `laravel: migraciones del nucleo`; `laravel: columnas de unidades por caja` |
| 1.6 | L1.4–L1.6 | `VerificacionSeeder` exacto y `DatabaseSeeder` (verificación + `SectorSeeder`) | `migrate:fresh --seeder=VerificacionSeeder` da 7 cuentas, 5 productos, 21 clientes, 11 oportunidades, 43 registros de historial y 4 actividades; `--seed` agrega la semilla sectorial sin alterar la semilla oficial | [E] «Escribe VerificacionSeeder con exactamente la semilla de la sección 11 del enunciado (te la pego), incluidos los 43 registros de historial con la fecha sugerida. Contraseñas con `Hash::make`. No ajustes ningún dato.» | `laravel: semilla de verificacion` |
| 1.7 | L2.2–L2.14 | Cuentas y roles: middleware `RolPanel` y `CuentaActiva`, CRUD de usuarios sin borrar, supervisor activo, D6, autoprotección del administrador | L2.1–L2.14 pasan a mano con la semilla | [E] «Implementa L2 de la especificación: login de Breeze que rechaza vendedores con mensaje hacia la SPA, middleware que expulsa cuentas desactivadas en la siguiente petición, administración de cuentas con FormRequest (mensajes de usted) y la decisión D6.» | `laravel: roles y acceso al panel`; `laravel: administracion de cuentas` |
| 1.8 | P1 (R4) | Aclaración del teléfono en la especificación (riesgo «Teléfono» de la sección 5), antes de la tarea 2.5 | `docs/especificacion.md` describe la regla del prefijo y sus ejemplos en 3.4 y en el control de cambios; **`spec-v1` no se mueve** (sigue en `90613b0`); el cambio queda en un commit aparte | [E] «Agrega a 3.4 de la especificación la aclaración del teléfono de la sección 5 del plan, con sus ejemplos y una línea en el control de cambios. No toques nada más.» | `spec: aclara normalizacion del telefono` |

**Pasos de 1.1 — cierre de la especificación y punto de partida limpio**

1. **`spec-v1` en GitHub.** `git push origin spec-v1`, y comprobar que `git ls-remote --tags origin`
   muestre `spec-v1` en `90613b0`. Ningún commit de código de la aplicación es anterior a esa etiqueta.
2. **Borrar la `laravel/` actual.** Hoy `laravel/` no es un proyecto de Laravel: solo tiene
   `composer.json` y `composer.lock` (con Breeze requerido en una carpeta vacía) y `vendor/`. No está en
   Git. Antes de borrarla se revisa su contenido (`ls -a laravel`) para no perder nada.
3. **Crear el proyecto.** Tarea 1.3, desde la raíz del repositorio.
4. **Comprobar que `.env` no se sube.**
   - `git check-ignore -v laravel/.env api/.env` muestra la regla de `.gitignore` que ignora cada uno.
   - `git status` no lista ningún `.env` (solo `.env.example`).
   - `git log --all --oneline -- '*.env' '**/.env'` no devuelve nada (S2).
   - `laravel/.env.example` y `api/.env.example` no tienen valores reales (`APP_KEY`, `DB_PASSWORD` y `JWT_SECRET` vacíos).
5. **Commit del proyecto base** (tarea 1.3), sin `vendor/` ni `node_modules/`: `git status` no los muestra.
6. **Comprobar que está en GitHub.** `git push origin main`. En la página del repositorio se ven
   `laravel/` con `artisan`, `composer.json` y `.env.example`, y no se ven `.env`, `vendor/` ni
   `node_modules/`. `git status -sb` muestra `main...origin/main` sin commits pendientes.

**Traspaso 1 → 2 (Integrante B):** clona o hace `git pull`; corre `migrate:fresh --seeder=VerificacionSeeder`;
revisa `SHOW CREATE TABLE` de las 7 tablas; repite los inserts de L1.3 y RP5; recorre L2.1–L2.14 en el
navegador; **lee el diff completo** (`git diff spec-v1..HEAD`). Anota lo encontrado en
`docs/verificacion.md` (matriz) y hace el commit `docs: verificacion del bloque 1`.

### Bloque 2 — Integrante B: A1, A2, L3, L4, F1 — **HECHO** (2026-11-08)

> Tareas 2.1–2.6 terminadas: `ab90dd6`, `3eddf1d`, `b5da01f`, `f63434a`, `f897d78`. Correcciones de
> la auditoría: `bfcdee2`, `be50f6e`, `241d490`, `62b4f31`, `1fff582`, `1ddf305`, `ae9afaa`. Spec 1.2:
> `563e6ce`. 88 pruebas en verde. Evidencia en `docs/verificacion.md` («Bloque 2»); entorno,
> pendientes y trampas en `docs/traspaso.md` («Traspaso del bloque 2 al bloque 3»). El bloque llegó
> antes en `e3273d5`, un commit sin prefijo que se descartó; `f897d78` usa `frontend:` en vez de `spa:`.

| # | Requisito | Tarea | Terminado cuando | Prompt al agente | Commits |
|---|-----------|-------|------------------|------------------|---------|
| 2.1 | A7 (base) | Base de la API: `app.js`, `db.js`, errores JSON, 404 JSON, 400 por JSON mal formado, 500 genérico | `npm start` levanta en :3000; una ruta inexistente y un JSON roto responden JSON; con MySQL apagado, 500 genérico y detalle solo en la consola | [E] «Arma la base de Express 5 de la sección 2 del plan: pool mysql2 con `decimalNumbers: true` y `timezone: 'Z'`, CORS con `CORS_ORIGIN`, manejo de errores según A7. Sin dotenv: `node --env-file`.» | `api: base Express, conexion y errores` |
| 2.2 | A1 | `POST /auth/login` | A1.1–A1.6 en Postman | [E] «Implementa A1: login con bcryptjs contra `users`, 401 idéntico para correo inexistente y clave incorrecta, 403 para no vendedores o desactivados, JWT HS256 con `sub` y `JWT_EXPIRES_IN`. Respuesta exacta de 3.2.» | `api: inicio de sesion con JWT` |
| 2.3 | A2 | Middleware de autenticación | A2.1–A2.3; token `none`, alterado y vencido dan 401 | [E] «Implementa A2: verifica el JWT solo con HS256 y relee al usuario en cada petición (activo y vendedor).» | `api: middleware de autenticacion` |
| 2.4 | L3, RP7 | Catálogo | L3.1–L3.5 y RP7 | [E] «Implementa L3: productos sin borrar (solo desactivar), precio > 0 con máximo 2 decimales validado en Laravel, nombre único y `unidades_por_caja` vacío o entero 1–999.» | `laravel: catalogo de productos` |
| 2.5 | L4 | Clientes y asignación: `Support/Nit.php` y `Telefono.php`, lista con búsqueda (P-8) y filtros, alta y edición, asignar (máximo 10, con TX), reasignar (D2), liberar (D3), eliminar, marca de D4 | L4.1–L4.17; todos los casos de `docs/casos-normalizacion.json`, incluidos los casos límite del teléfono de la sección 5, dan el resultado esperado en una prueba de Laravel | [E] «Implementa L4. NIT y teléfono según R4, 3.4 y la regla del teléfono de la sección 5 del plan (el prefijo `502` sin `+` solo se quita si quedan exactamente 11 dígitos), en clases de `app/Support`. Crea `docs/casos-normalizacion.json` con los casos de la sección 5 y una prueba de Laravel que los recorra. La asignación bloquea la fila del vendedor en `users` (`lockForUpdate`) antes de contar. La reasignación mueve solo las oportunidades abiertas y no toca actividades (D2). Liberar se rechaza con abiertas, con el mensaje del panel de D3.» | `docs: casos de normalizacion`; `laravel: clientes y normalizacion`; `laravel: asignacion, reasignacion y liberacion` |
| 2.6 | F1 | SPA: estructura, `api.js`, estado → render, login, cierre de sesión, 401 → login | F1.1–F1.5 **contra la API real** desde `http://127.0.0.1:5500` | [E] «Crea la base de la SPA de la sección 2 del plan (sin framework, estado → render, delegación de eventos) y la vista de login de F1. `localStorage` guarda solo el token. Todo texto de la API con `textContent` o escapado.» | `spa: base y sesion` |

**Traspaso 2 → 3 (Integrante A):** corre las peticiones de login y las de token alterado, vencido o
`none` en Postman; recorre L3 y L4 con la semilla; abre la SPA desde :5500 y desde otro origen (S6);
**lee el diff completo** del bloque 2. Commit `docs: verificacion del bloque 2`.

### Bloque 3 — Integrante A: L5, L6, L7, A3, A4, F2 — **HECHO** (2026-11-08)

> Tareas 3.1, 3.2, 3.3, 3.5 y 3.7 terminadas (3.7 sin probar el hito I2): `510138c`, `c73747a`, `7dbbf5f`, `e3d89a5`, `c7869d3`,
> `6a0b42a`. 3.6 parcial (`fe36cd2`, `c9e31bc`): falta la tabla cruzada de L7.2 y decidir las vistas de
> Breeze. 3.4 quedó completada con `ConcurrenciaSeeder` y `api/scripts/concurrencia-completa.mjs` (A3.8,
> A3.9 y duplicado de NIT). 3.2 y 3.5 quedaron en un commit cada una. 110 pruebas en
> verde. Evidencia en `docs/verificacion.md` («Bloque 3», `9a4f4c2`); entorno, pendientes y riesgos en
> `docs/traspaso.md` («Traspaso del bloque 3 al bloque 4»).

| # | Requisito | Tarea | Terminado cuando | Prompt al agente | Commits |
|---|-----------|-------|------------------|------------------|---------|
| 3.1 | L5, S5 (panel) | Equipos y visibilidad: políticas con 404, página del equipo | L5.1–L5.4 | [E] «Implementa L5: el supervisor solo ve su equipo y los disponibles; lo ajeno responde 404 aunque se escriba la URL; solo asigna a vendedores activos de su equipo, aunque se altere el formulario.» | `laravel: equipos y visibilidad` |
| 3.2 | L6, R7 | Oportunidades (solo lectura) y reporte del mes por vendedor y por producto | L6.1–L6.7 con las cifras exactas | [E] «Implementa L6 con Eloquent o el constructor de consultas, con enlaces de parámetros. El mes se agrupa en hora de Guatemala (UTC-6 fijo); el pronóstico es `ROUND(SUM(...), 2)` sobre las abiertas; total con el precio guardado en la línea.» | `laravel: oportunidades de solo lectura`; `laravel: reporte del mes` |
| 3.3 | A3, R3, R4, D5, P-7, P-8 | Endpoints de clientes; `validacion/nit.js` y `telefono.js` con los mismos casos | A3.1–A3.18; una prueba con `node:test` recorre `docs/casos-normalizacion.json` y da lo mismo que la prueba de Laravel | [E] «Implementa A3 y 3.1.1. La validación de NIT y teléfono debe dar exactamente lo mismo que `laravel/app/Support` (léelo), con la regla del teléfono de la sección 5 del plan. Escribe una prueba con `node:test` (sin dependencias) que recorra `docs/casos-normalizacion.json`. Cartera: TX con `SELECT … FOR UPDATE` de la fila del vendedor en `users`. Tomar: `UPDATE … WHERE vendedor_id IS NULL` + `affectedRows`. `ER_DUP_ENTRY` → 409.» | `api: clientes y cartera` |
| 3.4 | A3.8, A3.9, S8 | `ConcurrenciaSeeder` y `api/scripts/concurrencia-completa.mjs` | A3.8: uno 200 y nueve 409; A3.9: un éxito, dos 409 y Carlos en 10; dos altas con el mismo NIT: 201 y 409 | [E] «Escribe ConcurrenciaSeeder según la sección 2 de la especificación (no lo llames desde DatabaseSeeder) y un script Node sin dependencias que lance las peticiones simultáneas de A3.8, A3.9 y S8 e imprima los códigos.» | `laravel: semilla de concurrencia`; `api: script de concurrencia` |
| 3.5 | A4, R5, R6, D1, RP | Oportunidades, líneas y etapas; regla de unidades por caja | A4.1–A4.22, RP1–RP4, RP6 | [E] «Implementa A4 y 3.1.1: transiciones de D1, cambio de etapa + historial + fecha de cierre en una TX con `FOR UPDATE` de la oportunidad, precio y `unidades_por_caja` copiados con `INSERT … SELECT`, PATCH de cantidad contra la copia de la línea. Al crear, bloquea la fila del cliente (D3).» | `api: oportunidades y etapas`; `api: productos de la oportunidad y regla por caja` |
| 3.6 | L7 | Revisión de validación en el panel y prueba cruzada L7.2 | L7.1 en todos los formularios; L7.2 con los mismos datos en el panel y en la API | [E] «Revisa todos los FormRequest contra 3.4: mensajes en español, de usted, junto a cada campo y conservando lo escrito. Haz una tabla de casos de L7.2 y su resultado en las dos puertas.» | `laravel: validacion en el servidor`; cierre L7 en bloque 4 |
| 3.7 | F2 | Cartera, alta, disponibles y tomar | F2.1–F2.5 contra la API real; un cliente asignado en el panel aparece en la SPA | [E] «Implementa F2: cartera con búsqueda en el servidor y "N de 10", alta con errores del servidor junto a cada campo, disponibles con la acción de tomar y el mensaje del 409.» | `spa: cartera y disponibles` |

**Decisiones pendientes para la tarea 3.6 (L7)** (anotadas el 2026-11-08, al cerrar L2):

- **Verificación de correo.** El panel ya no exige `verified` (las cuentas las crea el administrador),
  pero siguen las rutas, controladores y vistas de verificación de Breeze (`verify-email`,
  `email/verification-notification`) y `EmailVerificationTest`. Decidir si se quitan.
- **Recuperación de contraseña.** Siguen `forgot-password` y `reset-password`, que envían un correo
  que no está configurado. Decidir si se quitan (y el administrador cambia la contraseña desde
  «Cuentas») o se configura el correo.
- **Textos en inglés.** El login y la navegación ya están en español; el resto de vistas de Breeze
  (perfil, recuperación, confirmación de contraseña, inicio) se traducen en L7.

**Traspaso 3 → 4 (Integrante B):** compara el reporte del panel con las cifras de control (L6.5–L6.7);
corre las peticiones de A3 y A4 en Postman y `scripts/concurrencia.js`; recorre F2; asigna un cliente en
el panel y lo ve en la SPA; **lee el diff completo** del bloque 3. Commit `docs: verificacion del bloque 3`.

### Bloque 4 — Integrante B: A5, A6, A7, A8, F3, F4, F5, F6

| # | Requisito | Tarea | Terminado cuando | Prompt al agente | Commits |
|---|-----------|-------|------------------|------------------|---------|
| 4.1 | A5, R8, P-6 | Actividades | A5.1–A5.14 | [E] «Implementa A5. La visibilidad sale de la cartera actual (`JOIN clientes … WHERE clientes.vendedor_id = ?`), nunca de `actividades.vendedor_id`. `vence_en` sin zona → 422; con zona → UTC. `vencida` se calcula al responder.» | `api: actividades` |
| 4.2 | A6, R7 | Resumen del mes | A6.1–A6.6; coincide con el panel (L5.3, L6.5) | [E] «Implementa A6 con SQL: ventas y total por dueño de la oportunidad, pronóstico sobre las abiertas, cartera actual, mes por defecto en hora de Guatemala; `?mes` inválido → 400.» | `api: resumen del mes` |
| 4.3 | A7 | Auditoría de códigos y formato | A7.1–A7.3; cada ruta responde con los códigos de 3.1 y 3.3 | [E] «Revisa cada ruta contra 3.1, 3.1.1 y 3.3 y hazme una tabla de discrepancias. No cambies el contrato.» | `api: codigos y errores segun contrato` |
| 4.4 | A8 | Colección de Postman y tabla de endpoints del README (con errores) | A8.2: una petición por endpoint, 401/403/404/409/422 y RP1–RP3; el README tiene la tabla completa de 3.1 | [E] «Completa `docs/api.postman_collection.json` con una petición por endpoint, los errores principales y RP1–RP3, con pruebas que comprueben código y forma. Agrega la columna de errores a la tabla del README.» | `docs: coleccion de Postman`; `docs: tabla de endpoints` |
| 4.5 | F3 | Embudo y detalle | F3.1–F3.4 | [E] «Implementa F3: tablero por etapa abierta, lista de cerradas, detalle con líneas e historial en hora de Guatemala, solo las transiciones de D1, motivo al perder; el total se muestra como lo devuelve el servidor.» | `spa: embudo y detalle` |
| 4.6 | F4 | Actividades | F4.1–F4.4 | [E] «Implementa F4: pendientes y completadas, vencidas marcadas, alta con fecha local enviada en ISO 8601 con zona, marcar y desmarcar.» | `spa: actividades` |
| 4.7 | F5 | Resumen del mes | F5.1–F5.3; con la zona de la computadora en UTC o Madrid, las fechas siguen en hora de Guatemala | [E] «Implementa F5 y `util/fechas.js` con hora de Guatemala fija, sin depender de la zona de la computadora; montos en quetzales.» | `spa: resumen del mes` |
| 4.8 | F6, S7 | Calidad: indicador de carga, API apagada, escape, 375 px | F6.1–F6.5 y S7 en la SPA y en el panel | [E] «Revisa toda la SPA contra F6: indicador de carga, mensaje con la API apagada, todo texto de la API escapado, sin desplazamiento horizontal a 375 px. Busca `innerHTML` con datos sin escapar y `{!! !!}` en Blade.» | `spa: calidad de la interfaz` |

**Traspaso 4 → cierre (Integrante A):** corre la colección de Postman completa con la semilla recién
cargada; recorre la SPA a 375 px y con otra zona horaria; prueba el cliente `<img src=x onerror=alert(1)>`
en la SPA y en el panel; **lee el diff completo** del bloque 4. Commit `docs: verificacion del bloque 4`.

### Cierre conjunto

| # | Quién | Requisito | Tarea | Terminado cuando | Prompt al agente | Commits |
|---|-------|-----------|-------|------------------|------------------|---------|
| C.1 | A | L1.5, L1.6, RP | `SectorSeeder` | Catálogo con precios en quetzales, al menos un producto con `unidades_por_caja = 24`, supervisores y vendedores propios, clientes (≤ 10 por vendedor), oportunidades en todas las etapas y actividades; sin correos, NIT ni teléfonos repetidos; `migrate:fresh --seed` sin pasos manuales | [E] «Escribe SectorSeeder según 1.1 y la sección 6 de la especificación. Comprueba con SQL que no repite correos, NIT ni teléfonos de la verificación y que nadie pasa de 10 clientes.» | `laravel: semilla del sector`; `laravel: completa etapas de semilla sector` |
| C.2 | A | P5 | Bitácora de IA (≥ 3 casos, cada uno con su commit corrector) | Cinco casos documentados con detección, commit corrector y lección | — | `docs: bitacora de errores de IA` |
| C.3 | B | P3, S1–S8 | `verificacion.md`: matriz de cada criterio con su evidencia, pruebas de seguridad (incluida la concurrencia) y cifras de control obtenidas en el panel y en la API | La matriz distingue evidencia ejecutada de las comprobaciones manuales pendientes; no inventa resultados | [E] «Arma la matriz de `docs/verificacion.md` con cada criterio de la especificación y su evidencia (petición de Postman, captura o consulta). Marca los que no tienen evidencia; no inventes resultados.» | `docs: matriz de verificacion` |
| C.4 | B | A8.1 | README con los pasos exactos desde cero | Requisitos, MySQL, configuración, semillas, tres piezas, pruebas, endpoints y errores documentados | [E] «Escribe los pasos del README para levantar MySQL, `laravel/`, `api/` y `frontend/` desde cero.» | `docs: instalacion y uso del CRM` |
| C.5 | A y B | Entrega | Ensayo de la defensa y etiqueta | `migrate:fresh --seeder=VerificacionSeeder`, las tres piezas corriendo, Postman abierto; `git tag entrega-final` y `git push origin entrega-final`; `git rev-parse entrega-final` se entrega en el aula virtual | — (lo hace la pareja) | — |

---

## 5. Riesgos de desalineación entre Laravel y la API

Las dos puertas escriben en las mismas tablas. Si validan o calculan distinto, el proyecto tiene un
defecto aunque cada una funcione sola (L7.2).

| Riesgo | Síntoma | Cómo se evita | Criterio que lo detecta |
|--------|---------|---------------|-------------------------|
| **NIT** normalizado distinto | `452 1817-k` repetido en una puerta y aceptado en la otra; `cf` aceptado en una sola | Una sola regla escrita (R4 + 3.4): quitar espacios y guiones, mayúsculas, quitar ceros a la izquierda, módulo 11, `CF` sin importar mayúsculas → NULL. `docs/casos-normalizacion.json` con entradas y resultado esperado, y las dos implementaciones se prueban con ese mismo archivo. La búsqueda `?q=` usa la misma función de compactar (P-8) | L4.3, L4.6, A3.5, A3.13, A3.18, L7.2 |
| **Teléfono** | `+502 5555-1234` repetido en una puerta y no en la otra; `5021 2345`, que es válido, rechazado porque se le quitó un «502» que no era prefijo | Regla decidida por la pareja (abajo), igual en las dos puertas y probada con el mismo archivo de casos (prueba de Laravel en 2.5 y `node:test` en 3.3). La aclaración se agrega a la especificación en un commit aparte (tarea 1.8), sin mover `spec-v1` | L4.4, A3.5, A3.14, L7.2 |
| **Zona horaria UTC-6** | Valeria con Q5,650.00 en octubre (agrupó en UTC); fechas corridas 6 h en el panel o en la SPA | Base y conexiones en UTC (`APP_TIMEZONE=UTC`, `'timezone' => '+00:00'`, mysql2 `timezone: 'Z'`, escrituras con `UTC_TIMESTAMP()`). Mes = `fecha_cierre - INTERVAL 6 HOUR` en las dos puertas. Para mostrar, desfase fijo de −6 h (Guatemala no tiene horario de verano) en `ZonaGuatemala.php` y en `util/fechas.js`; nunca la zona de la computadora | L6.4, L6.5, A6.1, A6.4, A5.3, F5.2 |
| **DECIMAL** | La API devuelve `"9250.00"` como texto; Q9,551.00 (precio del catálogo); diferencia de un centavo en el pronóstico | `decimalNumbers: true`; totales y pronóstico solo en SQL (`SUM`, `ROUND(…, 2)` de la suma, no por fila), con el precio guardado en la línea; nunca sumar con `parseFloat` en JS ni en PHP; en Laravel, casts `decimal:2` | A6.1–A6.3, L6.5, L3.5 |
| **Unidades por caja** | La API valida contra el catálogo y no contra la copia; `unidades_por_caja = 0` deja pasar todo (`x % 0` es NULL) | Copia en el mismo `INSERT … SELECT` que el precio; el PATCH valida contra la copia de la línea; `CHECK` con rango 1–999 en las dos tablas; el panel valida el mismo rango | RP1–RP7 |
| **Visibilidad de actividades por cartera** | Diego no ve la actividad del cliente que se le reasignó, o Valeria la sigue viendo; el panel cambia `actividades.vendedor_id` al reasignar | La API filtra siempre con `JOIN clientes … WHERE clientes.vendedor_id = ?` (lista y PATCH); la reasignación de Laravel no toca `actividades` (D2) | L4.8, A5.12–A5.14, F4.4 |
| **Cartera máxima entre las dos puertas** | El panel asigna y la API toma a la vez, y el vendedor queda con 11 | Las dos puertas bloquean **la misma fila** (`users.id` del vendedor, `FOR UPDATE` / `lockForUpdate`) antes de contar | L4.7, A3.7, A3.9 |
| **Cuenta desactivada o rol cambiado** | Un token sigue sirviendo tras desactivar en el panel | La API relee `rol` y `activo` en cada petición; el panel, en el middleware `CuentaActiva` | L2.9, A2.3 |

**Regla del teléfono** (decidida por la pareja; la tarea 1.8 la lleva a la especificación):

1. Quitar los separadores: espacios, guiones, puntos y paréntesis.
2. Si lo que queda empieza con `+502`, quitar ese prefijo.
3. Si no, y quedan **exactamente 11 dígitos** que empiezan con `502`, quitar el prefijo `502`.
4. El resultado debe cumplir `^[2-9][0-9]{7}$`; si no, es inválido (mensaje de 3.4).

Casos mínimos de `docs/casos-normalizacion.json` (los mismos para Laravel y la API):

| Entrada | Resultado | Por qué |
|---------|-----------|---------|
| `5021 2345` | `50212345` válido | 8 dígitos: el «502» es parte del número, no un prefijo |
| `502 5512 3456` | `55123456` válido | 11 dígitos que empiezan con 502: se quita el prefijo |
| `+502 5512 3456` | `55123456` válido | Empieza con `+502` |
| `+502 5021 2345` | `50212345` válido | Se quita `+502` y queda un número que empieza con 502 |
| `(502) 5512-3456` | `55123456` válido | Paréntesis y guion son separadores; quedan 11 dígitos |
| `502.5512.3456` | `55123456` válido | Puntos como separadores |
| `5555-1234` | `55551234` válido | Sin prefijo |
| `502 551 2345` | inválido | 10 dígitos: no se quita el prefijo y no son 8 |
| `+502 551 2345` | inválido | Se quita `+502` y quedan 7 dígitos |
| `+502 1234 5678` | inválido | Empieza con 1 |
| `0212 3456` | inválido | Empieza con 0 |
| `00502 5512 3456` | inválido | El prefijo `00502` no se acepta |
| `5512+3456` | inválido | El `+` solo vale al inicio |
| `5512 345a` | inválido | Las letras no son separadores |
| _(vacío)_ | inválido | Obligatorio |

---

## 6. Orden de integración

La SPA **nunca** trabaja con datos simulados: desde F1 se prueba contra la API real, servida por Live
Server en el origen exacto de `CORS_ORIGIN`.

| Hito | Cuándo | Qué se prueba de punta a punta |
|------|--------|--------------------------------|
| I1 | Fin de la tarea 2.6 (bloque 2) | Primera petición real de la SPA: CORS desde :5500 y rechazo desde otro origen. Alicia crea un vendedor en el panel y ese vendedor entra en la SPA (hash de Laravel verificado por bcryptjs) |
| I2 | Fin de la tarea 3.7 (bloque 3) | **Prueba central:** el supervisor asigna un cliente en el panel y aparece en la cartera de la SPA. Un alta con un NIT repetido se rechaza igual en las dos puertas (L7.2) |
| I3 | Fin de la tarea 3.5 (bloque 3) | Una venta ganada por la API (Postman) cambia el reporte del panel con la regla de UTC-6 |
| I4 | Fin de la tarea 4.7 (bloque 4) | La misma venta, ganada desde la SPA, aparece en el panel; el resumen de la SPA coincide con la página del equipo |
| I5 | Cierre (C.4) | Un clon limpio siguiendo solo el README levanta las tres piezas y da las cifras de control |

---

## 7. Reserva para el cambio de requisito (spec-v2)

El catedrático publicará un cambio de requisito durante el proyecto. Este espacio se llena cuando llegue.

**Procedimiento**

1. Copiar el anuncio tal cual en esta sección.
2. Actualizar `docs/especificacion.md`: requisitos y criterios nuevos o cambiados, modelo de datos y
   control de cambios. Commit `spec: cambio de requisito`; la pareja crea la etiqueta `spec-v2`.
3. Planificar aquí las tareas con el mismo formato de la sección 4. Las asume el integrante del bloque en
   curso, y el otro hace el traspaso.
4. Implementar **solo con migraciones nuevas** (las existentes no se editan) y sin romper los criterios
   anteriores: se vuelven a correr la colección de Postman y las cifras de control.

**Anuncio:** _(pendiente de publicación)_

| # | Requisito | Tarea | Terminado cuando | Prompt al agente | Commits |
|---|-----------|-------|------------------|------------------|---------|
| V2.1 | | | | | |
