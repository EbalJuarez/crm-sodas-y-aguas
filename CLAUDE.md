# CLAUDE.md — CRM de ventas · Distribuidora de agua purificada y gaseosas

Archivo de contexto del agente (entregable P4). Estas reglas se respetan **siempre**, en cada
sesión y en cada fase. Si una petición choca con alguna, el agente se detiene, lo dice y pregunta;
no la "resuelve" por su cuenta.

## Fuentes de verdad (en este orden)

1. `docs/Proyecto_Final_CRM_Enunciado.pdf` — el núcleo común: roles, modelo de datos, reglas R1–R8,
   contrato de la API (sección 8), semilla de verificación y cifras de control (sección 11).
   No se negocia.
2. `docs/especificacion.md` — las decisiones de la pareja. **Nunca se ignoran ni se reemplazan.**
   Si la especificación ya decidió algo, se implementa tal como está, aunque el agente prefiera
   otra opción. Si el agente cree que una decisión es incorrecta o choca con el enunciado, lo
   señala y espera; no la cambia en silencio.
3. `docs/plan.md` — fases, tareas y responsables.

Si la especificación no dice nada sobre un punto que hay que decidir (por ejemplo, una de las
preguntas abiertas de la sección 7 del enunciado), el agente **pregunta**; no decide por la pareja.

No se escribe código de implementación antes de que `docs/especificacion.md` esté terminada y
marcada en Git con la etiqueta `spec-v1`.

## El proyecto

- **Sector:** distribuidora de agua purificada y gaseosas (garrafones, fardos de botellas,
  gaseosas por caja, envases retornables, entrega a domicilio y a negocios). Precios en quetzales,
  del mercado guatemalteco.
- **Vocabulario:** la capa de sector cambia solo textos del panel y de la SPA. Rutas, campos del
  contrato y tablas del núcleo **no cambian** (siguen siendo `clientes`, `productos`,
  `oportunidades`, etc.). El vocabulario elegido vive en `docs/especificacion.md`.
- **Tres piezas, una base MySQL 8.4:**
  - `laravel/` — panel del administrador y del supervisor (Laravel 13 + Breeze Blade, PHP 8.4, Eloquent).
  - `api/` — API REST del vendedor (Node.js 24, Express 5, mysql2, jsonwebtoken, bcryptjs, cors).
  - `frontend/` — SPA del vendedor (HTML, Bootstrap 5.3, JavaScript con módulos).

## Regla propia del sector

> **Borrador — la versión vinculante es la de `docs/especificacion.md`.** Cuando la especificación
> la defina, este apartado se actualiza para coincidir con ella.

Punto de partida (ejemplo del enunciado para distribuidoras): *a un cliente con NIT de empresa
(no CF) no se le gana un pedido por debajo de un monto mínimo.*

Condiciones que debe cumplir, sea cual sea la redacción final:

- Se cumple en el servidor, en **las dos puertas** donde aplique, y en la base si es posible.
- Tiene criterios Dado / Cuando / Entonces en la especificación y una prueba en la colección de Postman.
- **No puede alterar el núcleo:** con solo `VerificacionSeeder` cargado el sistema se comporta
  exactamente como dice el enunciado y las cifras de control no cambian. Ojo: la semilla tiene
  clientes con NIT y oportunidades en negociación de montos bajos (p. ej. *Ampliación a bodega*,
  Q2,750.50); ganar desde negociación es un mínimo garantizado. Por eso la regla debe limitarse a
  los productos o datos propios del sector (p. ej. una columna nueva que acepte vacío), no a todos
  los pedidos.
- Una columna nueva en una tabla del núcleo acepta vacío o tiene valor por omisión, y llega con
  una **migración nueva**.

## Reglas de arquitectura (no se negocian)

- **Un solo dueño del esquema.** Las tablas se crean y modifican **solo** con migraciones de Laravel.
  - La API **nunca** ejecuta `CREATE`, `ALTER`, `DROP` ni ningún DDL. Ni scripts `.sql` de esquema en `api/`.
  - `users` se extiende con una migración propia; no se edita la que trae Laravel.
  - Las migraciones ya existentes no se editan: todo cambio llega con migraciones nuevas.
  - Llaves foráneas, índices únicos y restricciones `CHECK` del enunciado van en las migraciones.
- **Un solo creador de cuentas.** Las cuentas las crea el administrador en el panel. No hay
  registro público (la ruta y la vista de registro de Breeze se eliminan). Solo Laravel genera
  hashes; la API solo los verifica.
- **Sin ORM en la API.** Nada de Sequelize, Prisma, TypeORM, Knex ni constructores de consultas.
  SQL escrito a mano con `mysql2` y **siempre** con parámetros `?`. Nunca concatenar ni
  interpolar datos del usuario en el SQL (tampoco nombres de columna u orden: esos van por lista blanca).
- **Eloquent en el panel.** Nunca `DB::raw` (ni `whereRaw`, `selectRaw`, etc.) con datos del usuario
  sin enlace de parámetros.
- **`bcryptjs`, no `bcrypt`.** El paquete nativo `bcrypt` devuelve `false` con los hashes `$2y$` de PHP.
- **SPA sin framework.** HTML, Bootstrap 5.3 y JavaScript con módulos. Nada de React, Vue,
  Angular, Svelte, jQuery ni similares. Patrón estado → render y delegación de eventos.
  `localStorage` guarda **solo** la sesión (token).

## Datos, dinero y fechas

- **Dinero en `DECIMAL(10,2)`, nunca `FLOAT`/`DOUBLE`**, ni en migraciones ni en cálculos SQL.
  Los montos viajan como **números** JSON (configurar `mysql2` para que el DECIMAL no llegue como
  texto). Los totales y reportes los calcula la base (SQL en la API; Eloquent/constructor en el panel).
- El precio de una línea se **copia del catálogo** al agregarla; quien hace la petición nunca lo envía.
- **Fechas en UTC** en la base y en la API, en ISO 8601 (`2026-10-05T16:00:00.000Z`).
  - Una fecha que llega sin zona horaria se rechaza con **422**; con zona se convierte a UTC.
  - Los meses de los reportes se agrupan en hora de Guatemala (**UTC-6, sin horario de verano**).
  - La SPA y el panel muestran las fechas en hora de Guatemala, sin depender de la zona de la computadora.
- NIT y teléfono se normalizan **antes** de validar y comparar (R4). CF se guarda como `NULL` y la
  API lo devuelve como `"CF"`. La validación de R3 y R4 da el mismo resultado en el panel y en la API.

## Seguridad y respuestas

- **Lo ajeno responde 404, no 403**, en las dos puertas: un vendedor con datos de otro vendedor en
  la API, un supervisor con datos de otro equipo en el panel. La respuesta no confirma que exista.
- El dueño de un registro (`vendedor_id`, `usuario_id`) sale **del token o de la sesión**, nunca del cuerpo.
- La API nunca devuelve hashes, llaves internas (`vendedor_id`, `user_id`, `supervisor_id`) ni datos
  de otro vendedor. Los campos que el contrato no lista nunca se usan.
- En cada petición la API vuelve a leer al usuario: si está desactivado o ya no es vendedor, 401.
- Concurrencia: tomar un cliente, la cartera máxima (10), el cambio de etapa y los duplicados aguantan
  peticiones simultáneas (transacciones, bloqueos de fila o `UPDATE` condicionales). Para duplicados
  el árbitro final es el índice único de la base.
- CORS acepta **solo** el origen exacto de la SPA, configurado en `.env`. Nunca `*`.
- En la SPA, todo texto de la API se escapa o se inserta con `textContent`. En Blade, nunca `{!! !!}`
  con datos del usuario.
- Respuestas de la API siempre JSON: `{ "error": "..." }` o, para 422, `{ "errores": { campo: "..." } }`.
  Un 500 da un mensaje genérico; el detalle va solo a la consola del servidor.
- Los códigos de estado y las formas de respuesta son los de la sección 8 del enunciado. Se pueden
  **agregar** endpoints, nunca cambiar los del contrato.

## Mensajes al usuario

- **En español y tratando de usted**, en el panel, la API y la SPA:
  "Escriba un teléfono de Guatemala de 8 dígitos.", no "Escribe..." ni "Invalid phone".
- En Laravel, mensajes de validación en español (FormRequest o `validate`), con los datos escritos
  conservados y el mensaje junto a cada campo.

## Secretos y repositorio

- **`.env` nunca entra al repositorio** (ni en `laravel/` ni en `api/`). Solo `.env.example` sin
  valores reales. `JWT_SECRET` de al menos 32 caracteres aleatorios.
- Nunca hacer commit de `vendor/`, `node_modules/` ni archivos con credenciales. Si un secreto se
  filtró, borrarlo no basta: hay que cambiarlo.
- La contraseña pública de la semilla (`Ventas2026`) solo aparece en las semillas, el README y la
  colección de Postman.

## Semillas

- `VerificacionSeeder` carga **exactamente** la semilla de la sección 11 (incluidos los 43 registros
  de historial). No se "mejora" ni se ajusta.
- `SectorSeeder` corre después, con datos creíbles del sector, sin repetir correos, NIT ni teléfonos
  de la verificación y sin pasar de 10 clientes por vendedor.
- Con la semilla de verificación, panel y API deben dar las cifras de control de la sección 11.

## Cómo trabaja el agente

- Antes de cada fase, leer `docs/especificacion.md` y `docs/plan.md`.
- No agregar dependencias que el enunciado no pide sin preguntar.
- Cambios pequeños y revisables; explicar el porqué para que la pareja pueda defenderlo en la oral.
- Si el agente propuso algo incorrecto o distinto de la especificación y se corrigió, se registra en
  la bitácora de IA de `docs/verificacion.md` (P5).
- No hacer commits ni crear etiquetas sin que la pareja lo pida.
