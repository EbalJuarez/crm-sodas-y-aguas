# Especificación — CRM de ventas · Distribuidora de agua purificada y gaseosas

> **P1.** Se escribe antes del código y se marca en Git con la etiqueta `spec-v1`.
> Las decisiones las toma la pareja. Lo marcado **PENDIENTE: decidir** todavía no está decidido
> y no se implementa hasta que la pareja elija una opción y borre la marca.
>
> Fuente del núcleo: `docs/Proyecto_Final_CRM_Enunciado.pdf` (en este documento, «el enunciado»).
> Si algo de aquí choca con el núcleo, gana el enunciado y se corrige esta especificación.

**Estado:** borrador · **Versión:** pre `spec-v1`

### Pendientes de este documento

| # | Tema | Sección |
|---|------|---------|
| P-1 | Nombre de la empresa | 1.1 |
| P-2 | Vocabulario de las pantallas | 1.2 |
| P-3 | Regla propia | 6 |
| P-4 | Campos que el contrato no lista | 5.5 |
| P-5 | Visibilidad de las actividades de un cliente reasignado | 5.2 |
| P-6 | ¿Se puede desmarcar una actividad completada? | 5.6 |
| P-7 | `PATCH /clientes/:id` con un objeto sin campos editables | 5.6 |
| P-8 | Búsqueda `?q=` por NIT con guion o minúscula | 5.6 |

---

## 1. Sector y regla propia

### 1.1 Sector y empresa

Una **distribuidora de agua purificada y gaseosas** en Quetzaltenango. Vende a:

- **negocios** (tiendas, restaurantes, oficinas, colegios), que facturan con NIT y compran por
  volumen y de forma recurrente;
- **hogares**, en su mayoría consumidor final (CF), que compran recargas de garrafón.

Productos típicos del catálogo del sector (para `SectorSeeder`, con precios del mercado
guatemalteco): recarga de garrafón de 5 galones, envase de garrafón retornable, fardo de botellas
de 600 ml, caja de gaseosas, dispensador, servicio de entrega programada.

Los vendedores tienen una ruta y una cartera de hasta 10 clientes; los supervisores coordinan un
equipo de vendedores.

> **PENDIENTE: decidir (P-1) — nombre de la empresa.** Va en el título del panel, de la SPA y del
> README. No afecta reglas. Cualquier nombre inventado sirve; evite el de una empresa real.

### 1.2 Vocabulario de las pantallas

Solo cambian textos del panel y de la SPA. Rutas, campos JSON y tablas **no cambian**.

> **PENDIENTE: decidir (P-2).**
>
> | Opción | Núcleo → pantalla | A favor | En contra |
> |--------|-------------------|---------|-----------|
> | A. Mínimo | oportunidad → **pedido**; el resto igual | Es como habla el sector; poco que traducir | Poca capa de sector visible |
> | B. Completo | oportunidad → **pedido**, producto → **artículo**, actividad → **seguimiento**, cartera → **ruta** | Se siente propio del sector | Más textos que mantener y más riesgo de mezclar términos en la defensa |
> | C. Sin cambios | Todo como en el núcleo | Cero trabajo | No aprovecha la capa de sector |

### 1.3 Regla propia (resumen)

> **PENDIENTE: decidir (P-3).** Las opciones, con sus pros y contras, están en la **sección 6**.

---

## 2. Requisitos y criterios de aceptación

Formato: **Dado** (estado) / **Cuando** (acción) / **Entonces** (resultado comprobable).
Salvo que se diga otra cosa, «la semilla» es solo `VerificacionSeeder`
(`php artisan migrate:fresh --seeder=VerificacionSeeder`). Contraseña de todas las cuentas: `Ventas2026`.

Actores de la semilla: Alicia (administradora), Sergio y Sandra (supervisores), Valeria y Diego
(equipo de Sergio), Carlos (equipo de Sandra, cartera 9 de 10), Iván (equipo de Sandra, desactivado).

### 2.1 Panel Laravel

#### L1 — Esquema y semillas

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| L1.1 | Una base MySQL vacía | se ejecuta `php artisan migrate` | existen las tablas de la sección 4 con sus llaves foráneas, índices únicos y `CHECK` (comprobable con `SHOW CREATE TABLE`) |
| L1.2 | El proyecto Laravel recién creado | se revisa `database/migrations` | la migración original de `users` no está modificada; `rol`, `supervisor_id` y `activo` llegan en una migración propia |
| L1.3 | La base migrada | se intenta insertar con SQL directo una fila que viola un `CHECK` (p. ej. `productos.precio = 0`, `telefono = '12345678'`, etapa `perdida` sin motivo) | MySQL rechaza la fila |
| L1.4 | Una base con datos | se ejecuta `php artisan migrate:fresh --seeder=VerificacionSeeder` | la base contiene exactamente los datos de la sección 11 del enunciado (7 cuentas, 5 productos, 21 clientes, 11 oportunidades, 43 registros de historial, 4 actividades) |
| L1.5 | Una base con datos | se ejecuta `php artisan migrate:fresh --seed` | quedan cargadas las dos semillas, sin pasos manuales, y ningún vendedor pasa de 10 clientes |
| L1.6 | Las dos semillas cargadas | se buscan correos, NIT o teléfonos repetidos entre ellas | no hay ninguno |

#### L2 — Cuentas y roles

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| L2.1 | El panel en marcha | alguien abre `/register` | responde 404; el formulario de inicio de sesión no tiene enlace de registro |
| L2.2 | Alicia con sesión | crea una cuenta con nombre, correo, contraseña, rol y estado | la cuenta existe y su contraseña está guardada como hash bcrypt (`$2y$`) |
| L2.3 | Alicia crea o edita un vendedor | elige como supervisor una cuenta que no es supervisor activo, o no elige ninguno | el formulario vuelve con un mensaje junto al campo «supervisor» |
| L2.4 | Alicia crea o edita un administrador o supervisor | envía un supervisor | `supervisor_id` queda vacío |
| L2.5 | Alicia con sesión | busca la acción de borrar una cuenta | no existe; solo puede desactivarla |
| L2.6 | Alicia con sesión | intenta desactivarse o quitarse el rol de administradora | se rechaza con un mensaje |
| L2.7 | Valeria (vendedora) | inicia sesión en el panel | no entra; ve un mensaje que la envía a la SPA |
| L2.8 | Iván (desactivado) | inicia sesión en el panel | no entra |
| L2.9 | Sergio con sesión abierta | Alicia lo desactiva y Sergio hace otra petición | pierde la sesión y vuelve al inicio de sesión |

#### L3 — Catálogo

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| L3.1 | Alicia con sesión | agrega o edita un producto válido | se guarda |
| L3.2 | Alicia con sesión | escribe un precio `0`, negativo, no numérico o con más de dos decimales (`10.555`) | el formulario vuelve con un mensaje junto al precio |
| L3.3 | Alicia con sesión | escribe un nombre que ya existe | se rechaza con un mensaje junto al nombre |
| L3.4 | Alicia con sesión | busca la acción de borrar un producto | no existe; solo puede desactivarlo |
| L3.5 | La semilla | Alicia cambia el precio del Plan avanzado | *Renovación de planes* sigue con Q2,600.00 por unidad y el reporte de octubre de Valeria sigue en Q9,250.00 |

#### L4 — Clientes y asignación

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| L4.1 | Alicia con sesión | abre la lista de clientes | ve los 21 clientes, con búsqueda por nombre o NIT y filtros por vendedor y «sin asignar» |
| L4.2 | Sergio con sesión | abre la lista de clientes | ve los 5 clientes de Valeria y Diego y los 6 disponibles; no ve los 9 de Carlos ni el de Iván |
| L4.3 | Alicia o Sergio | crea un cliente con NIT `452 1817-k` | se rechaza como repetido de Comercial El Quetzal (se compara ya normalizado) |
| L4.4 | Alicia o Sergio | crea un cliente con teléfono `+502 5555-1234` | se rechaza como repetido |
| L4.5 | Alicia o Sergio | crea un cliente con NIT `CF` | se guarda con NIT vacío (NULL), aunque ya haya otros clientes CF |
| L4.6 | Alicia o Sergio | escribe un NIT con verificador incorrecto (`4521817-1`) o un teléfono que empieza en 0 o 1 | el formulario vuelve con el mensaje junto al campo |
| L4.7 | Carlos con 9 clientes | Alicia le asigna dos clientes disponibles | el primero se asigna; el segundo se rechaza por cartera llena |
| L4.8 | Un cliente asignado | se reasigna a otro vendedor activo | se aplica la decisión D2 (sección 5.2) |
| L4.9 | Un cliente con oportunidades abiertas | se intenta liberar | se aplica la decisión D3 (sección 5.3) |
| L4.10 | Un cliente sin oportunidades | Alicia lo elimina | se borra junto con sus actividades |
| L4.11 | Un cliente con al menos una oportunidad | Alicia intenta eliminarlo | se rechaza con un mensaje |
| L4.12 | Un vendedor desactivado | se intenta asignarle un cliente | se rechaza |

#### L5 — Equipos y visibilidad

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| L5.1 | Sergio con sesión | escribe a mano la URL de un cliente de Carlos (ver o editar) | responde 404 |
| L5.2 | Sergio con sesión | intenta asignar un cliente a Carlos (otro equipo) o a un vendedor desactivado, incluso alterando el formulario | se rechaza |
| L5.3 | Sergio con sesión | abre la página de su equipo | ve a Valeria (3 de 10, octubre: 2 ventas, Q9,250.00) y a Diego (2 de 10, octubre: 1 venta, Q6,000.00) |

#### L6 — Oportunidades y reportes

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| L6.1 | Alicia con sesión | abre las oportunidades | ve las 11, con filtro por etapa y por vendedor, total y fecha de cierre en hora de Guatemala |
| L6.2 | Sergio con sesión | abre las oportunidades | ve solo las 9 de Valeria y Diego |
| L6.3 | Cualquier usuario del panel | busca crear, editar o cambiar de etapa una oportunidad | no existe esa acción (solo lectura) |
| L6.4 | La semilla | se mira *Equipo para sucursal* | la fecha de cierre se muestra como 31/10/2026 21:30 (Guatemala) |
| L6.5 | La semilla | Alicia abre el reporte de octubre de 2026 | toda la empresa: 4 ventas, Q22,450.00, pronóstico Q6,751.13; por producto, las cuatro filas de la sección 11 |
| L6.6 | La semilla | Sergio abre el reporte de octubre de 2026 | ve solo su equipo: 3 ventas, Q15,250.00, pronóstico Q2,625.38 |
| L6.7 | La semilla | Alicia cambia el selector a noviembre de 2026 | Valeria: 1 venta, Q3,600.00 |

#### L7 — Validación en el servidor

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| L7.1 | Cualquier formulario del panel | se envía con datos inválidos (incluso quitando la validación HTML del navegador) | vuelve con los datos escritos y un mensaje en español, tratando de usted, junto a cada campo |
| L7.2 | Los mismos datos de cliente | se envían al panel y a la API | ambos aceptan o ambos rechazan, con el mismo motivo (NIT, teléfono, duplicados, cartera llena) |

### 2.2 API REST

#### A1 — Inicio de sesión con JWT

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A1.1 | La semilla | `POST /api/auth/login` con Valeria y `Ventas2026` | 200 con `{ token, usuario: { id, nombre, correo } }`, sin hash ni `supervisor_id` |
| A1.2 | La semilla | login correcto de Alicia, Sergio o Iván | 403 con `{ error }` y sin token |
| A1.3 | La semilla | login con un correo inexistente y, aparte, con Valeria y clave incorrecta | ambas responden 401 con exactamente el mismo cuerpo |
| A1.4 | La API en marcha | login sin `correo` o sin `clave` | 422 con `{ errores }` |
| A1.5 | La API en marcha | login con JSON mal formado | 400 |
| A1.6 | Un token de Valeria | se decodifica | algoritmo HS256, `sub` = id de Valeria, `exp` según `JWT_EXPIRES_IN` de `.env` |

#### A2 — Middleware de autenticación

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A2.1 | Cualquier ruta salvo el login | se llama sin `Authorization` | 401 |
| A2.2 | Un token de Valeria | se altera un carácter, vence, o se firma con otro algoritmo (incluido `none`) | 401 |
| A2.3 | Valeria con un token válido | Alicia la desactiva o le cambia el rol en el panel, y Valeria hace otra petición | 401 |

#### A3 — Clientes y cartera

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A3.1 | Valeria | `GET /clientes` | 200 con sus 3 clientes; `?q=Xela` devuelve solo Ferretería Xela; `?q=4521817` devuelve Comercial El Quetzal |
| A3.2 | Valeria | `GET /clientes/disponibles` | 200 con los 6 disponibles, cada uno solo `{ id, nombre }` |
| A3.3 | Valeria | `GET` o `PATCH /clientes/:id` de un cliente de Diego, o de un id que no existe | 404 con la misma respuesta en los dos casos |
| A3.4 | Valeria | `POST /clientes` válido | 201 con el cliente, ya en su cartera |
| A3.5 | Valeria | `POST /clientes` con NIT o teléfono de otro cliente (en cualquier formato) | 409 |
| A3.6 | Valeria | `POST /clientes` con NIT o teléfono inválido, o sin nombre | 422 con un mensaje por campo |
| A3.7 | Carlos (9 de 10) | crea un cliente y luego intenta tomar otro | el primero 201; el segundo 409 |
| A3.8 | Un cliente disponible | diez vendedores con espacio lo intentan tomar a la vez | exactamente uno recibe 200; los demás, 409 |
| A3.9 | Carlos (9 de 10) | envía a la vez dos tomas y un alta | solo una operación tiene éxito; Carlos termina con 10 |
| A3.10 | Valeria | `POST /clientes/:id/tomar` de un cliente ya asignado | 409; de un id que no existe, 404 |

#### A4 — Oportunidades y productos

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A4.1 | Valeria | `POST /oportunidades` con un cliente suyo | 201, etapa `prospecto`, total 0, un registro de historial con etapa anterior `null` |
| A4.2 | Valeria | `POST /oportunidades` con un cliente de Diego o disponible | 422 (error en `cliente_id`) |
| A4.3 | Valeria | `POST /oportunidades` con `etapa: "ganada"` o `vendedor_id` en el cuerpo | se aplica la decisión D5: nunca se usan esos campos |
| A4.4 | Una oportunidad de Valeria en prospecto | agrega el Plan básico con cantidad 3 | 201; la línea guarda Q1,200.00 y el total es 3,600 |
| A4.5 | La misma oportunidad | agrega otra vez el Plan básico | 409 |
| A4.6 | La misma oportunidad | agrega la Licencia anterior (inactiva) o un `producto_id` inexistente | 422 |
| A4.7 | La misma oportunidad | envía `precio_unitario: 1` al agregar | se ignora; la línea toma el precio del catálogo |
| A4.8 | Una línea | `PATCH` con cantidad 0, 1000 o 2.5 | 422 |
| A4.9 | *Ampliación a bodega* (negociación) | `PATCH /etapa` a `ganada` | 200, `fecha_cierre` presente, probabilidad 100, un registro nuevo en el historial |
| A4.10 | Una oportunidad sin productos en negociación | intenta ganarla | 422 y sin registro en el historial |
| A4.11 | Una oportunidad abierta | `PATCH /etapa` a `perdida` sin motivo o con solo espacios | 422 |
| A4.12 | *Renovación de planes* (ganada) | cambia título, etapa, o agrega, cambia o quita productos | 409 |
| A4.13 | Una oportunidad abierta | se piden transiciones que la decisión D1 no permite | 409 y sin registro en el historial |
| A4.14 | Una oportunidad en propuesta | llegan dos `PATCH /etapa` simultáneos a `negociacion` | uno recibe 200 y el otro 409; queda un solo registro nuevo en el historial |
| A4.15 | Valeria | `GET /oportunidades/:id` de una de Diego | 404 |
| A4.16 | Valeria | `GET /oportunidades?etapa=xyz` | 400 |

#### A5 — Actividades

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A5.1 | Valeria | `GET /actividades?estado=pendiente` | sus 2 pendientes; *Presentar opciones de soporte* (vence 2026-09-01) con `vencida: true` |
| A5.2 | Valeria | `GET /actividades?estado=otro` | 400 |
| A5.3 | Valeria | `POST /clientes/:id/actividades` con `vence_en: "2026-12-01T10:00:00-06:00"` | 201 y `vence_en` es `"2026-12-01T16:00:00.000Z"` |
| A5.4 | Valeria | la misma petición con `vence_en: "2026-12-01T10:00"` | 422 |
| A5.5 | Valeria | `tipo: "visita"` o descripción vacía o de más de 500 caracteres | 422 |
| A5.6 | Valeria | crea o modifica una actividad de un cliente de Diego | 404 |

#### A6 — Resumen del mes

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A6.1 | La semilla, Valeria | `GET /reportes/resumen?mes=2026-10` | `{ "mes": "2026-10", "ventas": 2, "total": 9250, "pronostico": 2512.88, "cartera": { "clientes": 3, "maximo": 10 } }`, montos como números (sin comillas) |
| A6.2 | La semilla, Valeria | `?mes=2026-11` | ventas 1, total 3600 |
| A6.3 | La semilla | Diego y Carlos piden octubre | Diego: 1, 6000, 112.5, 2 de 10. Carlos: 1, 7200, 4125.75, 9 de 10 |
| A6.4 | Valeria | sin `?mes` | el mes actual en hora de Guatemala |
| A6.5 | Valeria | `?mes=2026-13` u `?mes=octubre` | 400 |

#### A7 — Errores, códigos y formato

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A7.1 | Cualquier ruta, incluida una inexistente | responde con error | el cuerpo es JSON, nunca HTML |
| A7.2 | Una validación fallida | se responde | 422 con `{ "errores": { campo: mensaje } }`, un mensaje por campo, en español y tratando de usted |
| A7.3 | Un error inesperado (p. ej. MySQL apagado) | se responde | 500 con un mensaje genérico; el detalle queda solo en la consola |

#### A8 — Documentación

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A8.1 | Una computadora con las herramientas instaladas | alguien sigue el README desde cero | levanta las tres piezas sin pasos que no estén escritos |
| A8.2 | `docs/api.postman_collection.json` | se importa en Postman | tiene al menos una petición por endpoint, los errores principales (401, 403, 404, 409, 422) y la prueba de la regla propia |

### 2.3 SPA

#### F1 — Sesión

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| F1.1 | La SPA sin sesión | Valeria inicia sesión | entra y el token queda en `localStorage` |
| F1.2 | La SPA | Sergio inicia sesión | ve el mensaje del 403 de la API |
| F1.3 | Valeria con sesión | cierra sesión | el token se borra de `localStorage` |
| F1.4 | Valeria con sesión | cualquier petición responde 401 (token vencido o cuenta desactivada) | vuelve al inicio de sesión con un aviso |
| F1.5 | La SPA en uso | se revisa `localStorage` | solo contiene la sesión |

#### F2 — Cartera

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| F2.1 | Valeria | abre su cartera | ve sus clientes y «3 de 10»; la búsqueda consulta al servidor |
| F2.2 | Valeria | da de alta un cliente con NIT inválido | el mensaje del servidor aparece junto al NIT y lo escrito se conserva |
| F2.3 | Dos vendedores ven el mismo disponible | el segundo lo intenta tomar después del primero | ve el mensaje del 409 |

#### F3 — Embudo

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| F3.1 | Valeria | abre el embudo | ve una columna por etapa abierta y, aparte, la lista de cerradas |
| F3.2 | Valeria | abre *Renovación de planes* | ve productos con cantidad, precio guardado y subtotal, el total 5,650 y el historial en hora de Guatemala |
| F3.3 | Una oportunidad abierta | agrega, cambia cantidad y quita productos | el total se actualiza con lo que devuelve el servidor |
| F3.4 | Una oportunidad abierta | se ofrecen los cambios de etapa | solo aparecen los que permite D1; al perder, se pide el motivo |

#### F4 — Actividades

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| F4.1 | Valeria | abre actividades | ve pendientes y completadas; las vencidas se distinguen visualmente |
| F4.2 | Valeria | crea una actividad con fecha y hora local | la petición lleva ISO 8601 con zona (`-06:00` o `Z`) |
| F4.3 | Una pendiente | la marca como completada | se actualiza la lista |

#### F5 — Resumen del mes

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| F5.1 | Valeria | elige octubre de 2026 | ve 2 ventas, Q9,250.00, pronóstico Q2,512.88 y cartera 3 de 10 |
| F5.2 | La computadora con otra zona horaria (p. ej. UTC o Madrid) | se miran fechas en cualquier vista | se muestran en hora de Guatemala |

#### F6 — Calidad de la interfaz

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| F6.1 | El código de la SPA | se revisa | usa estado → render y delegación de eventos; sin framework |
| F6.2 | Una petición en curso | se espera la respuesta | se ve un indicador de carga |
| F6.3 | La API apagada | Valeria usa la SPA | ve un mensaje que lo explica, no una pantalla vacía |
| F6.4 | Un cliente llamado `<img src=x onerror=alert(1)>` | se muestra en cualquier vista | aparece como texto y no se ejecuta |
| F6.5 | El navegador a 375 px de ancho | se recorren todas las vistas | no hay desplazamiento horizontal |

### 2.4 Seguridad

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| S1 | La base y el código de la API | se revisan | solo Laravel genera hashes; la API solo llama a `bcryptjs.compare` |
| S2 | La tabla `users` y el repositorio | se revisan | no hay contraseñas en texto plano; `Ventas2026` solo aparece en semillas, README y Postman |
| S3 | El historial de Git | se busca `.env` en todos los commits | no aparece; existen `laravel/.env.example` y `api/.env.example` sin valores reales; `JWT_SECRET` tiene ≥ 32 caracteres aleatorios |
| S4 | La búsqueda `?q=` | se envía `' OR 1=1 -- ` | no devuelve clientes ajenos ni da error 500; en el código no hay SQL concatenado con datos del usuario ni `DB::raw` con ellos |
| S5 | Cualquier respuesta de la API | se revisa en Postman | no hay hashes, `vendedor_id`, `user_id`, `supervisor_id` ni datos de otro vendedor |
| S6 | Valeria intenta leer y modificar un cliente de Diego (API); Sergio, editar uno del equipo de Sandra (panel) | se hacen las peticiones | 404 en los dos casos |
| S7 | Una página servida desde otro origen | llama a la API | el navegador la bloquea; `CORS_ORIGIN` de `.env` tiene el origen exacto, nunca `*` |
| S8 | Un cliente con nombre `<img src=x onerror=alert(1)>` | se muestra en la SPA y en el panel | se ve como texto en los dos |
| S9 | Peticiones simultáneas | se repiten A3.8, A3.9, A4.14 y dos altas con el mismo NIT a la vez | se cumplen las reglas; en los duplicados uno recibe 201 y el otro 409 (árbitro: índice único) |

> S9 es la fila «Las reglas aguantan peticiones simultáneas» del enunciado (allí aparece junto a S8).

---

## 3. Contrato de la API

Base `http://localhost:3000/api`. JSON en las dos direcciones. Todas las rutas exigen
`Authorization: Bearer <token>`, salvo `POST /auth/login`. Estas rutas no se cambian; se pueden agregar otras.

### 3.1 Rutas

| Método | Ruta | Cuerpo o parámetros | Éxito | Errores |
|--------|------|---------------------|-------|---------|
| POST | `/auth/login` | `{ correo, clave }` | 200 `{ token, usuario }` | 400, 401, 403, 422 |
| GET | `/productos` | — | 200, `[Producto]` activos | 401 |
| GET | `/clientes` | `?q=` contiene, en nombre o NIT | 200, `[Cliente]` de su cartera | 401 |
| POST | `/clientes` | `{ nombre, nit, telefono, correo? }` | 201, `Cliente` ya en su cartera | 400, 401, 409, 422 |
| GET | `/clientes/disponibles` | — | 200, `[{ id, nombre }]` | 401 |
| POST | `/clientes/:id/tomar` | — | 200, `Cliente` ya en su cartera | 401, 404, 409 |
| GET | `/clientes/:id` | — | 200, `Cliente` | 401, 404 |
| PATCH | `/clientes/:id` | los campos que cambian | 200, `Cliente` | 400, 401, 404, 409, 422 |
| POST | `/clientes/:id/actividades` | `{ tipo, descripcion, vence_en? }` | 201, `Actividad` | 400, 401, 404, 422 |
| GET | `/actividades` | `?estado=pendiente\|completada` | 200, `[Actividad]` de los clientes de su cartera | 400, 401 |
| PATCH | `/actividades/:id` | `{ completada }` | 200, `Actividad` | 400, 401, 404, 422 |
| GET | `/oportunidades` | `?etapa=` | 200, `[ResumenOportunidad]` | 400, 401 |
| POST | `/oportunidades` | `{ cliente_id, titulo }` | 201, `DetalleOportunidad` en prospecto | 400, 401, 422 |
| GET | `/oportunidades/:id` | — | 200, `DetalleOportunidad` | 401, 404 |
| PATCH | `/oportunidades/:id` | `{ titulo }` | 200, `DetalleOportunidad` | 400, 401, 404, 409, 422 |
| PATCH | `/oportunidades/:id/etapa` | `{ etapa, motivo_perdida? }` (motivo solo al perder) | 200, `DetalleOportunidad` | 400, 401, 404, 409, 422 |
| POST | `/oportunidades/:id/productos` | `{ producto_id, cantidad }` | 201, `DetalleOportunidad` | 400, 401, 404, 409, 422 |
| PATCH | `/oportunidades/:id/productos/:lineaId` | `{ cantidad }` | 200, `DetalleOportunidad` | 400, 401, 404, 409, 422 |
| DELETE | `/oportunidades/:id/productos/:lineaId` | — | 204 sin cuerpo | 401, 404, 409 |
| GET | `/reportes/resumen` | `?mes=AAAA-MM` (por omisión, mes actual de Guatemala) | 200, `ResumenMes` | 400, 401 |

`:lineaId` es el `id` de la línea dentro de `productos` del detalle. Un `:lineaId` que no pertenece a
esa oportunidad responde 404.

### 3.2 Formas de respuesta

```jsonc
// Producto
{ "id": 1, "nombre": "Plan básico", "precio": 1200 }

// Cliente (también cada elemento de una lista). nit: compacto o "CF"; correo puede ser null
{ "id": 1, "nombre": "Comercial El Quetzal", "nit": "4521817K",
  "telefono": "55551234", "correo": "compras@elquetzal.test" }

// Login
{ "token": "eyJ...", "usuario": { "id": 4, "nombre": "Valeria López", "correo": "valeria@crm.test" } }

// ResumenOportunidad (lista): sin productos ni historial
{ "id": 1, "cliente": { "id": 1, "nombre": "Comercial El Quetzal" }, "titulo": "Renovación de planes",
  "etapa": "ganada", "probabilidad": 100, "total": 5650,
  "fecha_cierre": "2026-10-05T16:00:00.000Z", "motivo_perdida": null }

// DetalleOportunidad: ResumenOportunidad +
  "productos": [ { "id": 1, "producto_id": 2, "nombre": "Plan avanzado",
                   "cantidad": 2, "precio_unitario": 2600, "subtotal": 5200 } ],
  "historial": [ { "etapa_anterior": null, "etapa_nueva": "prospecto",
                   "usuario": "Valeria López", "fecha": "2026-09-23T16:00:00.000Z" } ]

// Actividad
{ "id": 2, "cliente": { "id": 2, "nombre": "Ferretería Xela" }, "tipo": "tarea",
  "descripcion": "Enviar propuesta de capacitación", "vence_en": "2030-01-15T15:00:00.000Z",
  "completada": false, "vencida": false }

// ResumenMes
{ "mes": "2026-10", "ventas": 2, "total": 9250, "pronostico": 2512.88,
  "cartera": { "clientes": 3, "maximo": 10 } }

// Errores
{ "error": "Su cartera ya tiene 10 clientes, el máximo permitido." }
{ "errores": { "nit": "Escriba un NIT válido (con su dígito verificador) o CF.",
               "telefono": "Escriba un teléfono de Guatemala de 8 dígitos." } }
```

Reglas de forma:

- Montos y probabilidades son **números** JSON, nunca texto.
- Fechas en ISO 8601 UTC con milisegundos y `Z`; `null` cuando no hay.
- `probabilidad`: prospecto 10, calificado 25, propuesta 50, negociacion 75, ganada 100, perdida 0.
- `vencida` = `completada` es falso **y** `vence_en` no es null **y** `vence_en` < ahora; se calcula al responder.
- `etapa` válidas: `prospecto`, `calificado`, `propuesta`, `negociacion`, `ganada`, `perdida` (sin tilde).
- `tipo` de actividad válidos: `llamada`, `reunion`, `tarea`.

### 3.3 Códigos de estado

| Código | Cuándo |
|--------|--------|
| 200 | Lectura o modificación correcta |
| 201 | Se creó un cliente, una oportunidad, una línea o una actividad |
| 204 | Se quitó un producto de una oportunidad |
| 400 | JSON mal formado, falta el cuerpo, o un filtro o parámetro no existe (`?etapa=xyz`, `?estado=x`, `?mes=2026-13`, `:id` no numérico) |
| 401 | Credenciales incorrectas; falta el token, está alterado o vencido, o la cuenta ya no es válida |
| 403 | Solo en el login: credenciales correctas, pero la cuenta no es de vendedor o está desactivada |
| 404 | No existe o es de otro vendedor (misma respuesta en los dos casos) |
| 409 | Choca con el estado: cartera llena, cliente ya tomado, NIT o teléfono repetido, producto repetido, oportunidad cerrada, transición no permitida (D1) |
| 422 | Datos que violan una regla: `{ errores }` con un mensaje por campo |
| 500 | Error inesperado; mensaje genérico, detalle solo en la consola |

### 3.4 Validaciones por campo (iguales en panel y API)

| Campo | Regla | Mensaje (ejemplo) |
|-------|-------|-------------------|
| `clientes.nombre` | obligatorio, 1–150 caracteres tras quitar espacios sobrantes | «Escriba el nombre del cliente.» |
| `clientes.nit` | obligatorio; `CF` o NIT válido por módulo 11 tras compactar; 2–12 caracteres compacto | «Escriba un NIT válido (con su dígito verificador) o CF.» |
| `clientes.telefono` | obligatorio; tras quitar espacios, guiones, puntos, paréntesis y `+502`/`502`, 8 dígitos que empiezan con 2–9 | «Escriba un teléfono de Guatemala de 8 dígitos.» |
| `clientes.correo` | opcional; formato de correo; ≤ 150 | «Escriba un correo válido.» |
| `oportunidades.titulo` | obligatorio, 1–150 tras quitar espacios sobrantes | «Escriba el título.» |
| `motivo_perdida` | obligatorio al perder, 1–255 tras quitar espacios sobrantes | «Escriba el motivo de la pérdida.» |
| `cantidad` | entero 1–999 | «La cantidad debe ser un número entero de 1 a 999.» |
| `actividades.tipo` | `llamada`, `reunion` o `tarea` | «Elija llamada, reunión o tarea.» |
| `actividades.descripcion` | obligatoria, 1–500 | «Escriba la descripción.» |
| `vence_en` | opcional; ISO 8601 **con** zona horaria | «Escriba la fecha con su zona horaria.» |
| `completada` | booleano JSON | «Indique verdadero o falso.» |

---

## 4. Modelo de datos y dónde se cumple cada regla

Todas las tablas las crean **migraciones de Laravel**. La API no ejecuta DDL.
Todas las fechas se guardan en UTC (`DATETIME`; Laravel con `APP_TIMEZONE=UTC` y mysql2 con
`timezone: 'Z'`). Todas las tablas tienen `created_at` y `updated_at`, salvo `historial_etapas`
(solo `created_at`).

Leyenda de «Dónde»: **M** migración (tipo / FK) · **CK** `CHECK` · **UQ** índice único ·
**LV** Laravel (FormRequest / política) · **API** Node · **TX** transacción o bloqueo.

> Restricción de MySQL: una columna usada en un `CHECK` no puede tener acción referencial
> (`ON DELETE SET NULL`/`CASCADE`). Por eso `users.supervisor_id` usa `RESTRICT`.

### users (migración propia que agrega columnas)

| Columna | Tipo | Regla | Dónde |
|---------|------|-------|-------|
| `rol` | `ENUM('administrador','supervisor','vendedor')` default `vendedor` | obligatorio | M, LV |
| `supervisor_id` | `BIGINT UNSIGNED NULL` FK `users.id` `RESTRICT` | obligatorio si vendedor, vacío si no | CK `(rol='vendedor') = (supervisor_id IS NOT NULL)`, LV |
| | | debe ser un supervisor **activo** (al asignar) | LV (entre filas: no cabe en un CHECK) |
| `activo` | `BOOLEAN` default `true` | no se borran cuentas | LV (sin acción de borrar) |
| `password` | hash bcrypt `$2y$` | solo Laravel lo genera | LV; API solo compara (bcryptjs) |
| — | — | el administrador no se desactiva ni se quita el rol a sí mismo | LV |

### productos

| Columna | Tipo | Regla | Dónde |
|---------|------|-------|-------|
| `nombre` | `VARCHAR(120)` | obligatorio y único | UQ, LV |
| `precio` | `DECIMAL(10,2)` | > 0, máx. 2 decimales | CK `precio > 0`, M (escala 2), LV |
| `activo` | `BOOLEAN` default `true` | solo activos se agregan; no se borran | LV, API |

### clientes

| Columna | Tipo | Regla | Dónde |
|---------|------|-------|-------|
| `vendedor_id` | `BIGINT UNSIGNED NULL` FK `users.id` | null = disponible | M |
| | | máx. 10 por vendedor, también con concurrencia | LV + API con TX (bloqueo `SELECT … FOR UPDATE` de la fila del vendedor en `users` antes de contar) |
| | | tomar es atómico | API: `UPDATE clientes SET vendedor_id=? WHERE id=? AND vendedor_id IS NULL` + `affectedRows` dentro de la TX |
| `nombre` | `VARCHAR(150)` | obligatorio | LV, API |
| `nit` | `VARCHAR(12) NULL` | compacto o NULL (CF); único | UQ (NULL no choca), CK `nit IS NULL OR nit REGEXP '^[0-9]{1,11}[0-9K]$'`, LV + API (módulo 11) |
| `telefono` | `CHAR(8)` | 8 dígitos, inicia 2–9; único | UQ, CK `telefono REGEXP '^[2-9][0-9]{7}$'`, LV + API (normalización) |
| `correo` | `VARCHAR(150) NULL` | formato válido | LV, API |
| — | — | duplicados con concurrencia | UQ es el árbitro final: el error `ER_DUP_ENTRY` se traduce a 409 (API) o a mensaje de campo (LV) |
| — | — | se elimina solo sin oportunidades | FK `oportunidades.cliente_id RESTRICT` + LV |

### oportunidades

| Columna | Tipo | Regla | Dónde |
|---------|------|-------|-------|
| `cliente_id` | FK `clientes.id` `RESTRICT` | del cliente de la cartera de quien crea | API |
| `vendedor_id` | FK `users.id` | dueño; sale del token | API (nunca del cuerpo) |
| `titulo` | `VARCHAR(150)` | obligatorio | API |
| `etapa` | `ENUM(6 etapas)` default `prospecto` | nace en prospecto; transiciones de D1 | M, API |
| `motivo_perdida` | `VARCHAR(255) NULL` | presente sii `perdida`; 1–255 sin espacios sobrantes | CK `(etapa='perdida') = (motivo_perdida IS NOT NULL)`, CK `motivo_perdida IS NULL OR CHAR_LENGTH(TRIM(motivo_perdida)) > 0`, API |
| `fecha_cierre` | `DATETIME NULL` | presente sii `ganada` o `perdida` | CK `(etapa IN ('ganada','perdida')) = (fecha_cierre IS NOT NULL)`, API (`UTC_TIMESTAMP()`) |
| — | — | ganar exige ≥ 1 producto | API (entre tablas) |
| — | — | cerrada no cambia (409) | API, dentro de la TX con `FOR UPDATE` |
| — | — | total = Σ cantidad × precio_unitario | API (SQL `SUM`) / LV (consulta) |

### oportunidad_productos

| Columna | Tipo | Regla | Dónde |
|---------|------|-------|-------|
| `oportunidad_id` | FK `oportunidades.id` `CASCADE` | al borrar la oportunidad se borran sus líneas | M |
| `producto_id` | FK `productos.id` `RESTRICT` | solo productos activos; una vez por oportunidad | UQ `(oportunidad_id, producto_id)`, API |
| `cantidad` | `SMALLINT` | 1–999 | CK `cantidad BETWEEN 1 AND 999`, API |
| `precio_unitario` | `DECIMAL(10,2)` | copia del catálogo al agregar; > 0 | CK `precio_unitario > 0`, API (`INSERT … SELECT precio FROM productos`) |

### historial_etapas

| Columna | Tipo | Regla | Dónde |
|---------|------|-------|-------|
| `oportunidad_id` | FK `CASCADE` | — | M |
| `usuario_id` | FK `users.id` | quién hizo el cambio (del token) | API |
| `etapa_anterior` | `ENUM NULL` | NULL solo en la creación | API |
| `etapa_nueva` | `ENUM` | obligatoria | M |
| `created_at` | `DATETIME` | cuándo (UTC) | API |
| — | — | se escribe en la misma TX que el cambio; un rechazo no deja registro; dos cambios simultáneos no dejan dos registros | API: TX + `SELECT … FOR UPDATE` de la oportunidad, se valida la etapa actual después del bloqueo |

### actividades

| Columna | Tipo | Regla | Dónde |
|---------|------|-------|-------|
| `cliente_id` | FK `clientes.id` `CASCADE` | al borrar el cliente se borran | M |
| `vendedor_id` | FK `users.id` | quién la registró (del token) | API |
| `tipo` | `ENUM('llamada','reunion','tarea')` | obligatorio | M, API |
| `descripcion` | `VARCHAR(500)` | obligatoria | API |
| `vence_en` | `DATETIME NULL` | con zona al recibir; UTC al guardar | API (422 sin zona) |
| `completada` | `BOOLEAN` default `false` | — | M |

### Reglas transversales

| Regla | Dónde |
|-------|-------|
| Lo ajeno es 404 | API (filtrar por dueño en el `WHERE`, nunca consultar y luego comparar); LV (políticas / `abort(404)`) |
| Reportes por mes de Guatemala | SQL con `DATE_FORMAT(fecha_cierre - INTERVAL 6 HOUR, '%Y-%m')` o rango UTC equivalente (API y LV) |
| Pronóstico | `ROUND(SUM(total × probabilidad / 100), 2)` sobre abiertas, sin filtro de mes (API y LV) |
| DECIMAL como número | mysql2 con `decimalNumbers: true` |
| Cuenta desactivada pierde acceso | LV (middleware en cada petición); API (relee el usuario en cada petición) |

---

## 5. Decisiones de la sección 7

### 5.1 D1 — Transiciones del embudo — **DECIDIDO**

**Se permite:** avanzar una etapa a la vez entre las abiertas
(prospecto → calificado → propuesta → negociacion); ganar **solo** desde negociacion; perder desde
**cualquier etapa abierta**. No hay saltos ni retrocesos. Cualquier otra transición (incluida la que
deja la etapa igual) responde **409**. Una etapa que no existe responde **422**.

| Desde \ a | calificado | propuesta | negociacion | ganada | perdida |
|-----------|:-:|:-:|:-:|:-:|:-:|
| prospecto | ✔ | | | | ✔ |
| calificado | | ✔ | | | ✔ |
| propuesta | | | ✔ | | ✔ |
| negociacion | | | | ✔ | ✔ |

**Alternativas descartadas:**
- *Solo el mínimo* (perder solo desde negociación): la semilla tiene *Plan familiar* perdida desde
  propuesta; un cliente puede desistir en cualquier momento y obligar a avanzar hasta negociación para
  perder falsea el embudo.
- *Retroceder una etapa*: más flexible, pero multiplica los casos a probar y complica el historial sin
  necesidad clara en el sector.
- *Libre entre abiertas*: el historial deja de reflejar el avance real y el pronóstico se puede inflar saltando a negociación.

### 5.2 D2 — Cliente reasignado — **DECIDIDO**

Las oportunidades **cerradas** (ganadas y perdidas) se quedan con su vendedor original: conserva el
crédito y los reportes de meses pasados no cambian. Las **abiertas** pasan al nuevo vendedor (se
actualiza `oportunidades.vendedor_id`), en la misma transacción que la reasignación.

**Alternativas descartadas:**
- *Todo pasa al nuevo*: cambia reportes ya cerrados y le quita el crédito a quien vendió.
- *Nada se mueve*: el nuevo vendedor no podría trabajar las oportunidades abiertas de su propio cliente (R2).

> **PENDIENTE: decidir (P-5) — actividades del cliente reasignado.** `actividades.vendedor_id` es
> «quién la registró», y R2 dice que el vendedor ve las actividades de los clientes de su cartera.
>
> | Opción | A favor | En contra |
> |--------|---------|-----------|
> | A. No se toca `vendedor_id`; el nuevo vendedor ve **todas** las actividades del cliente porque la visibilidad sale de la cartera | Conserva quién hizo cada cosa; consulta simple (`JOIN clientes`) | El nuevo vendedor ve actividades completadas del anterior |
> | B. Las **pendientes** cambian `vendedor_id` al nuevo; las completadas quedan, y el nuevo solo ve las suyas | Cada quien ve solo lo que le toca | Altera «quién la registró»; contradice la definición de la columna |
> | C. Igual que A, pero la SPA muestra «registrada por otro vendedor» en las ajenas | Transparente | Exige exponer un dato de otro vendedor (choca con R8) — probablemente descartable |

### 5.3 D3 — Liberar un cliente con oportunidades abiertas — **DECIDIDO**

**No se puede.** El panel rechaza la liberación con un mensaje («Cierre o reasigne las
oportunidades abiertas de este cliente antes de liberarlo.»). Con oportunidades solo cerradas, sí se
libera; las cerradas siguen con su vendedor original y no las ve quien tome el cliente después (R2).

**Alternativas descartadas:**
- *Liberar y marcarlas perdidas*: cambia el pronóstico y crea pérdidas que nadie decidió.
- *Liberar y dejarlas con el vendedor anterior*: quedan oportunidades abiertas de un cliente que no
  está en la cartera de su dueño, lo que rompe R2 y R5.

### 5.4 D4 — Cartera de un vendedor desactivado — **DECIDIDO**

Sus clientes **siguen asignados** a él hasta que el supervisor o el administrador los reasigne desde
el panel. El panel marca a esos clientes como «vendedor desactivado». No se pueden asignar clientes
nuevos a un vendedor desactivado. Si se reactiva, recupera su cartera tal como estaba.

**Alternativas descartadas:**
- *Liberar automáticamente*: choca con D3 cuando hay oportunidades abiertas.
- *Obligar a reasignar antes de desactivar*: deja sin acceso inmediato a alguien que debe salir ya
  (urgencia de seguridad), que es justamente el caso de uso de desactivar.

### 5.5 D5 — Campos que el contrato no lista — **PENDIENTE: decidir (P-4)**

En cualquier caso, esos campos **nunca se usan** (p. ej. `vendedor_id`, `etapa` al crear, `precio_unitario`).

| Opción | A favor | En contra |
|--------|---------|-----------|
| A. Ignorarlos en silencio (lista blanca de campos) | Simple; la SPA puede mandar objetos completos sin romperse; tolerante a versiones | Un error de escritura del cliente (`telefno`) pasa sin aviso |
| B. Rechazarlos con 422 (`errores.campo: "Este campo no se acepta."`) | Explícito; detecta errores y abusos | Más código y más casos en Postman; una SPA desactualizada falla |
| C. Ignorar en general, pero 422 para campos sensibles (`vendedor_id`, `precio_unitario`, `etapa` al crear) | Avisa justo donde hay intento de abuso | Dos comportamientos que explicar y probar |

### 5.6 Decisiones menores — **PENDIENTES**

> **P-6 — ¿Se puede desmarcar una actividad completada (`completada: false`)?**
> A. Sí: corrige errores del vendedor; en contra, se pierde el rastro de que estuvo completada.
> B. No, 409: el registro es fiel; en contra, un clic equivocado no tiene arreglo en la SPA.

> **P-7 — `PATCH /clientes/:id` con un objeto sin campos editables (`{}`)**
> A. 400 («falta el cuerpo»): coherente con la tabla de códigos.
> B. 200 sin cambios: idempotente y simple; en contra, oculta errores del cliente. Depende de D5.

> **P-8 — Búsqueda `?q=` con NIT escrito con guion o minúscula (`4521817-k`)**
> A. Compactar también `q` antes de buscar en `nit`: el vendedor encuentra lo que escribe.
> B. Buscar tal cual: más simple; en contra, `4521817-k` no encuentra nada.

---

## 6. Regla propia — **PENDIENTE: decidir (P-3)**

Condiciones que toda opción debe cumplir (enunciado, sección 3):

- Se cumple en el servidor (y en la base si aplica), tiene criterios Dado/Cuando/Entonces y una prueba en Postman.
- **Con solo `VerificacionSeeder` el sistema se comporta exactamente como el núcleo y las cifras de
  control no cambian.** Por eso las tres opciones dependen de una columna nueva en `productos`
  (nullable o con valor por omisión, en una **migración nueva**) que los 5 productos de la semilla no
  tienen: la regla nunca se activa con la semilla.
- En el panel las oportunidades son de solo lectura, así que la regla se cumple en la **API**; el
  panel solo administra la columna nueva en el catálogo (L3).

### Opción A — Envases retornables

*Un pedido con recargas de garrafón no puede ganarse si el cliente no tendría envases suficientes:
recargas de la oportunidad ≤ envases comprados por el cliente en ventas ganadas + envases incluidos en
esta oportunidad.*

- Columna nueva: `productos.tipo_envase ENUM('recarga','envase') NULL`.
- Dónde: API al pasar a `ganada` (consulta `SUM` en la misma TX). 409 o 422 con mensaje
  «El cliente no tiene envases suficientes para N recargas. Agregue envases al pedido.»
- Dado/Cuando/Entonces (ejemplo): Dado un cliente sin compras previas y un pedido en negociación con
  10 recargas y 0 envases, cuando se intenta ganar, entonces se rechaza; dado el mismo pedido con 10
  envases, entonces se gana.
- **A favor:** muy propia del sector (el garrafón retornable es el corazón del negocio); fácil de
  explicar en la defensa; prueba concreta.
- **En contra:** la consulta cruza ventas anteriores del cliente; hay que decidir si cuentan envases
  vendidos por otro vendedor (cliente reasignado) — sin exponerle esos datos (R8).

### Opción B — Pedido mínimo para negocios (ejemplo del enunciado)

*A un cliente con NIT (no CF) no se le gana un pedido de productos de la distribuidora por debajo de
un monto mínimo (p. ej. Q300.00), porque no cubre el costo de la entrega a negocio.*

- Columna nueva: `productos.de_distribucion BOOLEAN NULL` (o `categoria`); el mínimo, en
  `api/.env` o como constante documentada.
- Dónde: API al pasar a `ganada`. Solo cuenta el subtotal de los productos marcados.
- **A favor:** simple; viene sugerida en el enunciado, así que su sentido ya está aceptado.
- **En contra:** es el ejemplo del enunciado (otra pareja puede elegirla; menos original); limitarla a
  productos marcados para no romper la semilla se ve artificial y hay que justificarlo en la defensa.

### Opción C — Venta al detalle para consumidor final

*Un cliente CF (hogar) no puede tener en un pedido más de N unidades (p. ej. 20) de un producto de
mayoreo (fardos, cajas): esas ventas se facturan con NIT.*

- Columna nueva: `productos.max_cf SMALLINT NULL` (límite por producto; NULL = sin límite).
- Dónde: API al **agregar** o **cambiar cantidad** de una línea (422 en `cantidad`). Se puede reforzar
  en la base con un trigger en una migración nueva, porque un `CHECK` no puede leer otra tabla.
- **A favor:** se valida en un solo punto y con la respuesta ya definida (422 por campo); fácil de probar en Postman.
- **En contra:** un trigger es más difícil de defender; si se omite, la regla vive solo en la API.
  Además, si un cliente cambia de CF a NIT (o al revés) con el pedido abierto, hay que decidir qué pasa.

---

## 7. Control de cambios

| Versión | Etiqueta | Cambio |
|---------|----------|--------|
| 0.1 | — | Borrador inicial |
