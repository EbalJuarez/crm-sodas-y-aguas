# Especificación — CRM de ventas · Distribuidora de agua purificada y gaseosas

> **P1.** Se escribe antes del código y se marca en Git con la etiqueta `spec-v1`.
> Las decisiones las toma la pareja. Lo que todavía no esté decidido se marca como pendiente y no se
> implementa hasta que la pareja elija una opción y borre la marca.
>
> Fuente del núcleo: `docs/Proyecto_Final_CRM_Enunciado.pdf` (en este documento, «el enunciado»).
> Si algo de aquí choca con el núcleo, gana el enunciado y se corrige esta especificación.

**Estado:** spec-v1 (etiqueta en `90613b0`) · **Versión:** 1.1 (aclaraciones posteriores a la etiqueta en la sección 7)

### Pendientes de este documento

Ninguno. Las decisiones P-1 a P-8 están cerradas (secciones 1, 5 y 6).

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

**Empresa (nombre provisional): Distribuidora Aguaviva.** Va en el título del panel, de la SPA y del
README. No afecta reglas. Es un nombre inventado; si se cambia, solo cambian esos textos.

### 1.2 Vocabulario de las pantallas — **DECIDIDO**

Solo cambian textos del panel y de la SPA. Rutas, campos JSON, tablas y los mensajes de la API
**no cambian** (la API sigue diciendo «cliente», «oportunidad», «actividad»).

| Núcleo | En pantalla | Plural |
|--------|-------------|--------|
| cliente | **punto de venta** | puntos de venta |
| oportunidad | **pedido por cerrar** | pedidos por cerrar |
| actividad | **visita o llamada** | visitas y llamadas |
| etapas | sin cambios: prospecto, calificado, propuesta, negociación, ganada, perdida | — |
| producto, cartera, vendedor, supervisor, tipos de actividad | sin cambios | — |

**Alternativas descartadas:**
- *Vocabulario completo* (además producto → artículo, cartera → ruta): más textos que mantener y más
  riesgo de mezclar términos en la defensa.
- *Sin cambios*: no aprovecha la capa de sector.

### 1.3 Regla propia (resumen) — **DECIDIDO**

**Venta por caja completa:** un producto que se vende por caja o fardo solo entra a un pedido en
múltiplos de sus unidades por caja. Detalle, criterios y alternativas descartadas en la **sección 6**.

---

## 2. Requisitos y criterios de aceptación

Formato: **Dado** (estado) / **Cuando** (acción) / **Entonces** (resultado comprobable).
Salvo que se diga otra cosa, «la semilla» es solo `VerificacionSeeder`
(`php artisan migrate:fresh --seeder=VerificacionSeeder`). Todas las cuentas usan la contraseña pública
de las semillas (sección 11 del enunciado). La única excepción es **A3.8** (y su repetición en S8),
que además necesita `ConcurrenciaSeeder` (abajo).

Actores de la semilla: Alicia (administradora), Sergio y Sandra (supervisores), Valeria y Diego
(equipo de Sergio), Carlos (equipo de Sandra, cartera 9 de 10), Iván (equipo de Sandra, desactivado,
con Hotel Los Pinos asignado).

**`ConcurrenciaSeeder`** — solo para la prueba de concurrencia A3.8:

- No forma parte de `DatabaseSeeder` ni de `SectorSeeder`; se ejecuta a mano después de la
  verificación: `php artisan db:seed --class=ConcurrenciaSeeder`.
- Crea 10 vendedores activos del equipo de Sergio, sin clientes, con correos
  `concurrencia01@crm.test` … `concurrencia10@crm.test` y la contraseña pública de las semillas. El
  hash lo genera Laravel (`Hash::make`), como en las demás semillas.
- No crea clientes, productos, oportunidades ni actividades, así que las cifras de ventas y pronóstico
  no cambian; sí cambia lo que ve Sergio en su equipo. Por eso la defensa se hace sin ella.

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
| L2.10 | La semilla | Alicia inicia sesión con su correo y la contraseña pública | entra al panel; con una contraseña incorrecta no entra y ve un mensaje en español |
| L2.11 | Alicia con sesión | cierra sesión y luego abre una página interna del panel | vuelve al inicio de sesión |
| L2.12 | La semilla | Alicia intenta cambiar el rol de Sergio (tiene a Valeria y Diego) | se rechaza con el mensaje junto a «rol»: «Este supervisor tiene vendedores a su cargo. Reasigne primero a sus vendedores a otro supervisor.» |
| L2.13 | La semilla | Alicia intenta cambiar el rol de Valeria (3 clientes) | se rechaza con el mensaje junto a «rol»: «Esta cuenta tiene clientes asignados. Reasigne primero sus clientes.» |
| L2.14 | La semilla | Alicia desactiva a Sergio | se permite; Valeria y Diego conservan su supervisor, y al editarlos L2.3 exige elegir un supervisor activo |

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
| L4.8 | La semilla | Alicia reasigna Comercial El Quetzal de Valeria a Diego (D2) | *Ampliación a bodega* y *Seguimiento anual* pasan a Diego; *Renovación de planes* sigue siendo de Valeria. Valeria: octubre 2 ventas, Q9,250.00, pronóstico Q450.00, cartera 2 de 10. Diego: octubre 1 venta, Q6,000.00, pronóstico Q2,175.38, cartera 3 de 10. El historial no cambia. Ninguna actividad se modifica: *Confirmar fecha de instalación* (completada, registrada por Valeria) ahora la ve Diego y ya no Valeria |
| L4.9 | La semilla | Alicia o Sergio intenta liberar Comercial El Quetzal (tiene *Ampliación a bodega* y *Seguimiento anual* abiertas) (D3) | se rechaza con el mensaje del panel de D3; el cliente, sus oportunidades y sus actividades siguen igual y en la cartera de Valeria |
| L4.10 | Un cliente sin oportunidades | Alicia lo elimina | se borra junto con sus actividades |
| L4.11 | Un cliente con al menos una oportunidad | Alicia intenta eliminarlo | se rechaza con un mensaje |
| L4.12 | Un vendedor desactivado | se intenta asignarle un cliente | se rechaza |
| L4.13 | La semilla | Alicia o Sergio libera a María Coyoy (solo tiene oportunidades cerradas) | queda disponible (7 disponibles); Valeria queda con 2 de 10; *Soporte del hogar* y *Plan familiar* siguen siendo de Valeria y su reporte de noviembre sigue en 1 venta, Q3,600.00; *Presentar opciones de soporte* no se modifica, pero ya no aparece entre las actividades de Valeria (le queda 1 pendiente) |
| L4.14 | La semilla | Alicia o Sergio edita Ferretería Xela con teléfono `5555 1234` o NIT `4521817K` | se rechaza como repetido junto al campo; guardar Ferretería Xela sin cambiar su NIT ni su teléfono no choca consigo misma |
| L4.15 | La semilla | Alicia abre la lista de clientes (D4) | Hotel Los Pinos aparece asignado a Iván con la marca «vendedor desactivado» y no está entre los disponibles |
| L4.16 | La semilla | Sandra reasigna Hotel Los Pinos a Carlos | se reasigna; Carlos queda con 10 de 10 |
| L4.17 | La semilla | Alicia reactiva a Iván | Hotel Los Pinos sigue en su cartera (1 de 10) |

#### L5 — Equipos y visibilidad

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| L5.1 | Sergio con sesión | escribe a mano la URL de un cliente de Carlos (ver o editar) | responde 404 |
| L5.2 | Sergio con sesión | intenta asignar un cliente a Carlos (otro equipo) o a un vendedor desactivado, incluso alterando el formulario | se rechaza |
| L5.3 | Sergio con sesión | abre la página de su equipo | ve a Valeria (3 de 10, octubre: 2 ventas, Q9,250.00) y a Diego (2 de 10, octubre: 1 venta, Q6,000.00) |
| L5.4 | Sergio con sesión | revisa la página de su equipo y el filtro por vendedor de clientes y oportunidades | no aparecen Carlos ni Iván, solo Valeria y Diego |

#### L6 — Oportunidades y reportes

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| L6.1 | Alicia con sesión | abre las oportunidades | ve las 11, con filtro por etapa y por vendedor, total y fecha de cierre en hora de Guatemala |
| L6.2 | Sergio con sesión | abre las oportunidades | ve solo las 9 de Valeria y Diego |
| L6.3 | Cualquier usuario del panel | busca crear, editar o cambiar de etapa una oportunidad | no existe esa acción (solo lectura) |
| L6.4 | La semilla | se mira *Equipo para sucursal* | la fecha de cierre se muestra como 31/10/2026 21:30 (Guatemala) |
| L6.5 | La semilla | Alicia abre el reporte de octubre de 2026 | toda la empresa: 4 ventas, Q22,450.00, pronóstico Q6,751.13. Por vendedor: Valeria 2 ventas, Q9,250.00, pronóstico Q2,512.88; Diego 1, Q6,000.00, Q112.50; Carlos 1, Q7,200.00, Q4,125.75; Iván 0, Q0.00, Q0.00. Por producto, las cuatro filas de la sección 11 |
| L6.6 | La semilla | Sergio abre el reporte de octubre de 2026 | ve solo su equipo: 3 ventas, Q15,250.00, pronóstico Q2,625.38; por vendedor, solo Valeria y Diego con las cifras de L6.5 |
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
| A1.1 | La semilla | `POST /api/auth/login` con Valeria y la contraseña pública | 200 con `{ token, usuario: { id, nombre, correo } }`, sin hash ni `supervisor_id` |
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

> A2.3 «le cambia el rol» se prueba con una vendedora sin clientes (L2.13 impide cambiar el rol de
> Valeria mientras tenga cartera): por ejemplo, después de liberar o reasignar sus clientes.

#### A3 — Clientes y cartera

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A3.1 | Valeria | `GET /clientes` | 200 con sus 3 clientes; `?q=Xela` devuelve solo Ferretería Xela; `?q=4521817` devuelve Comercial El Quetzal |
| A3.2 | Valeria | `GET /clientes/disponibles` | 200 con los 6 disponibles, cada uno solo `{ id, nombre }` |
| A3.3 | Valeria | `GET` o `PATCH /clientes/:id` de un cliente de Diego, o de un id que no existe | 404 con la misma respuesta en los dos casos. **Excepción explícita (D2):** si el cliente era de Valeria y se reasignó, sus oportunidades cerradas siguen mostrando `cliente: { id, nombre }`, aunque `GET /clientes/:id` le responda 404 |
| A3.4 | Valeria | `POST /clientes` válido | 201 con el cliente, ya en su cartera |
| A3.5 | Valeria | `POST /clientes` con NIT o teléfono de otro cliente (en cualquier formato) | 409 |
| A3.6 | Valeria | `POST /clientes` con NIT o teléfono inválido, o sin nombre | 422 con un mensaje por campo |
| A3.7 | Carlos (9 de 10) | crea un cliente y luego intenta tomar otro | el primero 201; el segundo 409 |
| A3.8 | La semilla y `ConcurrenciaSeeder` | los 10 vendedores de `ConcurrenciaSeeder` intentan tomar Gimnasio Fuerza a la vez | exactamente uno recibe 200; los otros nueve, 409 |
| A3.9 | Carlos (9 de 10) | envía a la vez dos tomas y un alta | solo una operación tiene éxito; Carlos termina con 10 |
| A3.10 | Valeria | `POST /clientes/:id/tomar` de un cliente ya asignado | 409; de un id que no existe, 404 |
| A3.11 | Valeria | `GET /clientes/:id` de Comercial El Quetzal y de María Coyoy | 200 con la forma `Cliente`; María Coyoy trae `"nit": "CF"` y `"correo": null` |
| A3.12 | Valeria | `PATCH /clientes/:id` de Ferretería Xela con `{ "telefono": "7761-2399" }` | 200 con `"telefono": "77612399"`; los demás campos no cambian |
| A3.13 | Valeria | `PATCH` de Ferretería Xela con `{ "nit": "cf" }` | 200 con `"nit": "CF"`; en la base, `nit` es NULL |
| A3.14 | Valeria | `PATCH` de Ferretería Xela con `{ "telefono": "+502 7763 4567" }` (de Farmacia Los Altos) | 409; con `{ "nit": "98765434" }` (el suyo), 200 |
| A3.15 | Valeria | `PATCH` de Ferretería Xela con `{ "nit": "4521817-1" }` o `{ "nombre": "  " }` | 422 con el mensaje en `nit` o en `nombre` |
| A3.16 | Valeria | `PATCH` de Ferretería Xela con `{}` o con `{ "telefno": "55550000" }` (P-7) | 400; el cliente no cambia |
| A3.17 | Valeria | `PATCH` de Ferretería Xela con `{ "nombre": "Ferretería Xela Centro", "vendedor_id": 5 }` (D5) | 200 con el nombre nuevo; el cliente sigue en la cartera de Valeria |
| A3.18 | Valeria | `GET /clientes?q=4521817-k` y `?q=452 1817K` (P-8) | 200 con Comercial El Quetzal en los dos casos |

#### A4 — Oportunidades y productos

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A4.1 | Valeria | `POST /oportunidades` con un cliente suyo | 201, etapa `prospecto`, total 0, un registro de historial con etapa anterior `null` |
| A4.2 | Valeria | `POST /oportunidades` con un cliente de Diego o disponible | 422 (error en `cliente_id`) |
| A4.3 | Valeria | `POST /oportunidades` con `etapa: "ganada"` y `vendedor_id` (el de Diego) en el cuerpo | 201 sin error (D5: se ignoran); la oportunidad nace en `prospecto` y es de Valeria (Diego no la ve: 404) |
| A4.4 | Una oportunidad de Valeria en prospecto | agrega el Plan básico con cantidad 3 | 201; la línea guarda Q1,200.00 y el total es 3600 |
| A4.5 | La misma oportunidad | agrega otra vez el Plan básico | 409 |
| A4.6 | La misma oportunidad | agrega la Licencia anterior (inactiva) o un `producto_id` inexistente | 422 |
| A4.7 | La misma oportunidad | envía `precio_unitario: 1` al agregar | se ignora; la línea toma el precio del catálogo |
| A4.8 | Una línea | `PATCH` con cantidad 0, 1000 o 2.5 | 422 |
| A4.9 | *Ampliación a bodega* (negociación) | `PATCH /etapa` a `ganada` | 200, `fecha_cierre` presente, probabilidad 100, un registro nuevo en el historial |
| A4.10 | Una oportunidad sin productos en negociación | intenta ganarla | 422 con el mensaje en `errores.etapa` («Agregue al menos un producto antes de ganar la oportunidad.») y sin registro en el historial |
| A4.11 | Una oportunidad abierta | `PATCH /etapa` a `perdida` sin motivo o con solo espacios | 422 |
| A4.12 | *Renovación de planes* (ganada) | cambia título, etapa, o agrega, cambia o quita productos | 409 |
| A4.13 | La semilla | se piden transiciones que D1 no permite: *Seguimiento anual* (prospecto) a `propuesta`, `negociacion`, `ganada` o `prospecto`; *Mantenimiento* (calificado) a `prospecto` o `negociacion`; *Capacitación de personal* (propuesta) a `calificado`, `ganada` o `propuesta`; *Ampliación a bodega* (negociación) a `propuesta` o `negociacion` | 409 en cada una; la etapa no cambia y el historial sigue con 43 registros |
| A4.14 | Una oportunidad en propuesta | llegan dos `PATCH /etapa` simultáneos a `negociacion` | uno recibe 200 y el otro 409; queda un solo registro nuevo en el historial |
| A4.15 | Valeria | `GET /oportunidades/:id` de una de Diego | 404 |
| A4.16 | Valeria | `GET /oportunidades?etapa=xyz` | 400 (parámetro de query inexistente) |
| A4.17 | Valeria | `PATCH /oportunidades/:id/etapa` de *Seguimiento anual* con `{ "etapa": "xyz" }` o sin `etapa` | 422 con el mensaje en `errores.etapa` (campo del cuerpo inválido) |
| A4.18 | Valeria | `GET /productos` | 200 con 4 productos (Plan básico, Plan avanzado, Instalación, Soporte anual), sin Licencia anterior; `precio` como número (`2750.5`) |
| A4.19 | Valeria | `PATCH /oportunidades/:id` de *Seguimiento anual* con `{ "titulo": "Seguimiento anual 2027" }` | 200 con el detalle y el título nuevo; con `{ "titulo": "   " }`, 422 en `titulo` |
| A4.20 | *Capacitación de personal* (Instalación 2 × Q450.00) | `PATCH …/productos/:lineaId` con `{ "cantidad": 3 }` | 200; subtotal 1350 y total 1350 |
| A4.21 | La misma oportunidad | `DELETE …/productos/:lineaId` de esa línea | 204 sin cuerpo; el detalle queda sin productos y con total 0 |
| A4.22 | Valeria | `PATCH` o `DELETE` sobre *Capacitación de personal* con el `:lineaId` de una línea de *Renovación de planes* | 404 |

#### A5 — Actividades

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A5.1 | Valeria | `GET /actividades?estado=pendiente` | sus 2 pendientes; *Presentar opciones de soporte* (vence 2026-09-01) con `vencida: true` |
| A5.2 | Valeria | `GET /actividades?estado=otro` | 400 |
| A5.3 | Valeria | `POST /clientes/:id/actividades` con `vence_en: "2026-12-01T10:00:00-06:00"` | 201 y `vence_en` es `"2026-12-01T16:00:00.000Z"` |
| A5.4 | Valeria | la misma petición con `vence_en: "2026-12-01T10:00"` | 422 |
| A5.5 | Valeria | `tipo: "visita"` o descripción vacía o de más de 500 caracteres | 422 |
| A5.6 | Valeria | crea o modifica una actividad de un cliente de Diego | 404 |
| A5.7 | Valeria | `GET /actividades` sin `?estado` | 200 con sus 3 actividades (2 pendientes y *Confirmar fecha de instalación*, completada) |
| A5.8 | Valeria | `GET /actividades?estado=completada` | 200 con *Confirmar fecha de instalación*, `vencida: false` |
| A5.9 | Valeria | `PATCH /actividades/:id` de *Enviar propuesta de capacitación* con `{ "completada": true }` | 200 con `completada: true` y `vencida: false` |
| A5.10 | Valeria | `PATCH /actividades/:id` con `{ "completada": "si" }` o sin `completada` | 422 en `completada` |
| A5.11 | Valeria | `PATCH /actividades/:id` de *Confirmar fecha de instalación* (completada, venció 2026-10-08) con `{ "completada": false }` (P-6) | 200 con `completada: false` y `vencida: true` |
| A5.12 | La semilla, después de L4.8 (Comercial El Quetzal reasignado a Diego) | Diego y Valeria piden `GET /actividades` | Diego: 2 (*Renovación de licencias* y *Confirmar fecha de instalación*, que registró Valeria); Valeria: 2 (*Enviar propuesta de capacitación* y *Presentar opciones de soporte*). Ninguna fila de `actividades` cambió |
| A5.13 | El mismo estado | Diego hace `PATCH` de *Confirmar fecha de instalación*; Valeria intenta lo mismo | Diego: 200 (el cliente está en su cartera); Valeria: 404 |
| A5.14 | La semilla, después de L4.13 (María Coyoy liberada) | Valeria pide `GET /actividades?estado=pendiente` | 1 (*Enviar propuesta de capacitación*); *Presentar opciones de soporte* no la ve ningún vendedor mientras el cliente esté disponible |

> A5 filtra siempre por la **cartera actual**: una actividad es visible para el vendedor que hoy
> tiene al cliente (`JOIN clientes … WHERE clientes.vendedor_id = ?`), sea pendiente o completada y
> la haya registrado quien la haya registrado. Con la semilla sola, cada actividad es de un cliente
> que está en la cartera de quien la registró, así que A5.1–A5.11 no cambian.

#### A6 — Resumen del mes

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A6.1 | La semilla, Valeria | `GET /reportes/resumen?mes=2026-10` | `{ "mes": "2026-10", "ventas": 2, "total": 9250, "pronostico": 2512.88, "cartera": { "clientes": 3, "maximo": 10 } }`, montos como números (sin comillas) |
| A6.2 | La semilla, Valeria | `?mes=2026-11` | ventas 1, total 3600 |
| A6.3 | La semilla | Diego y Carlos piden octubre | Diego: 1, 6000, 112.5, 2 de 10. Carlos: 1, 7200, 4125.75, 9 de 10 |
| A6.4 | Valeria | sin `?mes` | el mes actual en hora de Guatemala |
| A6.5 | Valeria | `?mes=2026-13` u `?mes=octubre` | 400 |
| A6.6 | La semilla, después de L4.8 | Valeria y Diego piden `?mes=2026-10` | Valeria: ventas 2, total 9250, pronóstico 450, cartera 2 de 10. Diego: ventas 1, total 6000, pronóstico 2175.38, cartera 3 de 10 |

> En el resumen, `cartera` cuenta la cartera actual. `ventas` y `total` salen de las oportunidades
> ganadas cuyo dueño (`oportunidades.vendedor_id`) es quien pregunta: por D2 la venta cerrada conserva
> su vendedor aunque el cliente se reasigne (*Renovación de planes* sigue sumando para Valeria).
> `pronostico` suma las abiertas del vendedor; por D2 y D3 una oportunidad abierta siempre es del
> vendedor que hoy tiene al cliente, así que coincide con la cartera actual. Con la semilla sola, A6.1–A6.3 no cambian.

#### A7 — Errores, códigos y formato

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A7.1 | Cualquier ruta, incluida una inexistente | responde con error | el cuerpo es JSON, nunca HTML |
| A7.2 | Una validación fallida | se responde | 422 con `{ "errores": { campo: mensaje } }`, un mensaje por campo, en español y tratando de usted |
| A7.3 | Un error inesperado (p. ej. MySQL apagado) | se responde | 500 con un mensaje genérico; el detalle queda solo en la consola |

#### A8 — Documentación

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| A8.1 | Una computadora con las herramientas instaladas | alguien sigue el README desde cero | levanta las tres piezas sin pasos que no estén escritos; el README tiene la tabla de endpoints de la sección 3.1 (método, ruta, cuerpo, respuesta y errores) |
| A8.2 | `docs/api.postman_collection.json` | se importa en Postman | tiene al menos una petición por endpoint, los errores principales (401, 403, 404, 409, 422) y la prueba de la regla propia (RP1–RP3 de la sección 6) |

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
| F2.4 | La semilla, Valeria | abre la lista de disponibles | ve los 6, solo con su nombre, cada uno con la acción de tomarlo |
| F2.5 | La misma lista | Valeria toma Gimnasio Fuerza | Gimnasio Fuerza aparece en su cartera, sale de los disponibles y el contador pasa a «4 de 10», sin recargar la página |

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
| F4.4 | La semilla, después de L4.8 | Valeria y Diego abren actividades | la lista muestra solo las de los clientes de su cartera actual: *Confirmar fecha de instalación* aparece en la de Diego y ya no en la de Valeria |

#### F5 — Resumen del mes

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| F5.1 | Valeria | elige octubre de 2026 | ve 2 ventas, Q9,250.00, pronóstico Q2,512.88 y cartera 3 de 10 |
| F5.2 | La computadora con otra zona horaria (p. ej. UTC o Madrid) | se miran fechas en cualquier vista | se muestran en hora de Guatemala |
| F5.3 | La semilla, después de L4.8 | Valeria elige octubre de 2026 | ve 2 ventas, Q9,250.00, pronóstico Q450.00 y cartera 2 de 10 (A6.6) |

#### F6 — Calidad de la interfaz

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| F6.1 | El código de la SPA | se revisa | usa estado → render y delegación de eventos; sin framework |
| F6.2 | Una petición en curso | se espera la respuesta | se ve un indicador de carga |
| F6.3 | La API apagada | Valeria usa la SPA | ve un mensaje que lo explica, no una pantalla vacía |
| F6.4 | Un cliente llamado `<img src=x onerror=alert(1)>` | se muestra en cualquier vista | aparece como texto y no se ejecuta |
| F6.5 | El navegador a 375 px de ancho | se recorren todas las vistas | no hay desplazamiento horizontal |

### 2.4 Seguridad

Numeración de la sección 10 del enunciado.

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| S1 | La tabla `users`, el código de la API y el repositorio | se revisan | solo Laravel genera hashes (`$2y$`); la API solo llama a `bcryptjs.compare`; no hay contraseñas en texto plano en la base; la contraseña pública de las semillas solo aparece en las semillas, el README y la colección de Postman |
| S2 | El historial de Git | se busca `.env` en todos los commits | no aparece; existen `laravel/.env.example` y `api/.env.example` sin valores reales; `JWT_SECRET` tiene ≥ 32 caracteres aleatorios |
| S3 | La búsqueda `?q=` | se envía `' OR 1=1 -- ` | no devuelve clientes ajenos ni da error 500; en el código no hay SQL concatenado con datos del usuario ni `DB::raw` con ellos |
| S4 | Cualquier respuesta de la API | se revisa en Postman | no hay hashes, `vendedor_id`, `user_id`, `supervisor_id` ni datos de otro vendedor (el cliente de una venta propia no lo es: ver la aclaración a R8 en D2) |
| S5 | Valeria intenta leer y modificar un cliente de Diego (API); Sergio, editar uno del equipo de Sandra (panel) | se hacen las peticiones | 404 en los dos casos |
| S6 | Una página servida desde otro origen | llama a la API | el navegador la bloquea; `CORS_ORIGIN` de `.env` tiene el origen exacto, nunca `*` |
| S7 | Un cliente con nombre `<img src=x onerror=alert(1)>` | se muestra en la SPA y en el panel | se ve como texto en los dos |
| S8 | Peticiones simultáneas | se repiten A3.8 (con `ConcurrenciaSeeder`), A3.9, A4.14 y dos altas con el mismo NIT a la vez | se cumplen las reglas; en los duplicados uno recibe 201 y el otro 409 (árbitro: índice único) |

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
| PATCH | `/clientes/:id` | los campos que cambian, entre `nombre`, `nit`, `telefono`, `correo` | 200, `Cliente` | 400, 401, 404, 409, 422 |
| POST | `/clientes/:id/actividades` | `{ tipo, descripcion, vence_en? }` | 201, `Actividad` | 400, 401, 404, 422 |
| GET | `/actividades` | `?estado=pendiente\|completada` (opcional; sin él, todas) | 200, `[Actividad]` de los clientes de su cartera | 400, 401 |
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

### 3.1.1 Detalle de los endpoints con reglas propias del contrato

| Endpoint | Detalle |
|----------|---------|
| Todos los que reciben cuerpo | Solo se leen los campos que lista el contrato; el resto se ignora sin error (D5). |
| `GET /productos` | Solo productos con `activo = 1`, con la forma `Producto`. |
| `GET /clientes` | `?q=` busca «contiene» en `nombre` (tal cual) o en `nit` (con `q` compactado como R4) (P-8). Sin `q`, toda su cartera. |
| `GET /clientes/:id` | Solo un cliente de su cartera; cualquier otro (ajeno, disponible o inexistente) responde 404. |
| `PATCH /clientes/:id` | Edita `nombre`, `nit`, `telefono` y `correo`; cada campo enviado se valida con 3.4. `nit: "CF"` (en cualquier mayúscula o minúscula) se guarda como NULL y se devuelve `"CF"`. NIT y teléfono se normalizan y se comparan con los **demás** clientes: un repetido responde 409, y el propio valor no choca consigo mismo. Dato inválido: 422 en el campo. Cliente que no es de su cartera: 404. `vendedor_id` nunca sale del cuerpo. Sin ningún campo editable (`{}` o solo campos ignorados): 400 (P-7). |
| `GET /actividades` | Sin `?estado`, todas las actividades de los clientes de su cartera; `pendiente` = `completada` falso; `completada` = verdadero; cualquier otro valor, 400. |
| `PATCH /actividades/:id` | `completada` debe ser booleano JSON (`true`/`false`); otro tipo o ausente: 422 en `completada`. `false` sobre una completada la vuelve a pendiente (P-6). Actividad de un cliente que no es de su cartera: 404. |
| `PATCH /oportunidades/:id` | Solo cambia `titulo` (3.4). Oportunidad cerrada: 409. Título inválido: 422 en `titulo`. Ajena: 404. |
| `POST`, `PATCH /oportunidades/:id/productos[/:lineaId]` | `cantidad` según 3.4 y la regla propia (sección 6): 422 en `cantidad`. Oportunidad cerrada: 409. Producto repetido: 409. `:lineaId` de otra oportunidad: 404. |
| `DELETE /oportunidades/:id/productos/:lineaId` | 204 sin cuerpo. Oportunidad cerrada: 409. `:lineaId` de otra oportunidad: 404. |
| `PATCH /oportunidades/:id/etapa` | `etapa` ausente o que no es una de las 6: 422 en `etapa`. Transición que D1 no permite u oportunidad cerrada: 409. Ganar sin productos: 422 en `etapa`. Perder sin motivo válido: 422 en `motivo_perdida`. En ningún rechazo se escribe historial. |

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
- La regla propia no agrega campos: `Producto` y las líneas del detalle no exponen `unidades_por_caja`.

### 3.3 Códigos de estado

| Código | Cuándo |
|--------|--------|
| 200 | Lectura o modificación correcta |
| 201 | Se creó un cliente, una oportunidad, una línea o una actividad |
| 204 | Se quitó un producto de una oportunidad |
| 400 | La petición no se puede interpretar: JSON mal formado, falta el cuerpo, un parámetro de **query** con un valor que no existe (`?etapa=xyz`, `?estado=x`, `?mes=2026-13`), un `:id` de ruta no numérico, o un `PATCH /clientes/:id` sin ningún campo editable (P-7) |
| 401 | Credenciales incorrectas; falta el token, está alterado o vencido, o la cuenta ya no es válida |
| 403 | Solo en el login: credenciales correctas, pero la cuenta no es de vendedor o está desactivada |
| 404 | No existe o es de otro vendedor (misma respuesta en los dos casos) |
| 409 | Choca con el estado: cartera llena, cliente ya tomado, NIT o teléfono repetido, producto repetido, oportunidad cerrada, transición no permitida (D1) |
| 422 | Un campo del **cuerpo** viola una regla: `{ errores }` con un mensaje por campo. Incluye ganar sin productos (`etapa`) y la regla propia (`cantidad`) |
| 500 | Error inesperado; mensaje genérico, detalle solo en la consola |

**400 contra 422.** Un mismo nombre puede dar códigos distintos según dónde llegue:
`GET /oportunidades?etapa=xyz` es un filtro de query que no existe → **400**;
`PATCH /oportunidades/:id/etapa` con `{ "etapa": "xyz" }` es un campo del cuerpo inválido → **422**
en `etapa`. Del mismo modo, `?estado=x` → 400 y `{ "completada": "x" }` → 422.

### 3.4 Validaciones por campo (iguales en panel y API)

| Campo | Regla | Mensaje (ejemplo) |
|-------|-------|-------------------|
| `clientes.nombre` | obligatorio, 1–150 caracteres tras quitar espacios sobrantes | «Escriba el nombre del cliente.» |
| `clientes.nit` | obligatorio; `CF` o NIT válido por módulo 11 tras compactar; 2–12 caracteres compacto | «Escriba un NIT válido (con su dígito verificador) o CF.» |
| `clientes.telefono` | obligatorio; tras quitar espacios, guiones, puntos, paréntesis y `+502`/`502`, 8 dígitos que empiezan con 2–9 (cuándo se quita el prefijo: aclaración abajo) | «Escriba un teléfono de Guatemala de 8 dígitos.» |
| `clientes.correo` | opcional; formato de correo; ≤ 150 | «Escriba un correo válido.» |
| `oportunidades.titulo` | obligatorio, 1–150 tras quitar espacios sobrantes | «Escriba el título.» |
| `motivo_perdida` | obligatorio al perder, 1–255 tras quitar espacios sobrantes | «Escriba el motivo de la pérdida.» |
| `cantidad` | entero 1–999; si la línea tiene `unidades_por_caja`, múltiplo de ese número (sección 6) | «La cantidad debe ser un número entero de 1 a 999.» / «Este producto se vende por caja de 24 unidades: la cantidad debe ser múltiplo de 24.» |
| `actividades.tipo` | `llamada`, `reunion` o `tarea` | «Elija llamada, reunión o tarea.» |
| `actividades.descripcion` | obligatoria, 1–500 | «Escriba la descripción.» |
| `vence_en` | opcional; ISO 8601 **con** zona horaria | «Escriba la fecha con su zona horaria.» |
| `completada` | booleano JSON | «Indique verdadero o falso.» |

**Aclaración (versión 1.1): prefijo `502` del teléfono.** Un teléfono válido puede empezar con 502
(`5021 2345`), así que el prefijo no se quita a ciegas. La normalización, igual en el panel y en la API, es:

1. Se quitan los separadores: espacios, guiones, puntos y paréntesis.
2. Si lo que queda empieza con `+502`, se quita ese prefijo.
3. Si no, y quedan **exactamente 11 dígitos** que empiezan con `502`, se quita el prefijo `502`.
4. El resultado debe cumplir `^[2-9][0-9]{7}$`; si no, es inválido.

Ejemplos: `5021 2345` queda `50212345` (válido: 8 dígitos, el 502 es parte del número);
`502 5512 3456` queda `55123456` (11 dígitos que empiezan con 502); `+502 5512 3456` queda `55123456`.

Solo en el panel (catálogo): `productos.nombre` obligatorio, ≤ 120, único; `productos.precio` número
> 0 con 2 decimales como máximo; `productos.unidades_por_caja` vacío o entero de 1 a 999.

---

## 4. Modelo de datos y dónde se cumple cada regla

Todas las tablas las crean **migraciones de Laravel**. La API no ejecuta DDL.
Todas las fechas se guardan en UTC (`DATETIME`; Laravel con `APP_TIMEZONE=UTC` y mysql2 con
`timezone: 'Z'`). Todas las tablas tienen `created_at` y `updated_at`, salvo `historial_etapas`
(solo `created_at`).

Leyenda de «Dónde»: **M** migración (tipo / FK) · **CK** `CHECK` · **UQ** índice único ·
**LV** Laravel (FormRequest / política) · **API** Node · **TX** transacción o bloqueo.
La columna «Regla» remite a R1–R8 del enunciado, a S1–S8, a una decisión (D1–D6) o a la regla propia (RP).

> Restricción de MySQL: una columna usada en un `CHECK` no puede tener acción referencial
> (`ON DELETE SET NULL`/`CASCADE`). Por eso `users.supervisor_id` usa `RESTRICT`.

### Índice R1–R8

| Regla | Dónde se cumple (filas de abajo) |
|-------|----------------------------------|
| R1 Acceso por rol | `users.rol`, `users.activo`; transversales «Rol por puerta» y «Cuenta desactivada» |
| R2 Propiedad | `clientes.vendedor_id`, `oportunidades.vendedor_id`, `actividades.cliente_id`; transversal «Lo ajeno es 404» |
| R3 Cartera | `clientes.vendedor_id` (máximo 10, tomar, disponibles), D2 |
| R4 Identidad | `clientes.nit`, `clientes.telefono`, duplicados |
| R5 Embudo | `oportunidades.*`, `historial_etapas` |
| R6 Dinero | `productos.precio`, `productos.activo`, `oportunidad_productos.*`, total |
| R7 Reportes | transversales «Reportes por mes», «Pronóstico», «DECIMAL como número» |
| R8 Respuestas y fechas | `actividades.vence_en`, `vencida`; transversales «Respuestas sin datos internos» y «Fechas» |

### users (migración propia que agrega columnas)

| Columna | Tipo | Restricción | Dónde | Regla |
|---------|------|-------------|-------|-------|
| `rol` | `ENUM('administrador','supervisor','vendedor')` default `vendedor` | obligatorio; decide la puerta | M; LV (middleware: solo administrador y supervisor entran al panel); API (login 403 si no es vendedor; middleware 401 si ya no lo es) | R1 |
| | | no se cambia el rol de un supervisor con vendedores ni el de un vendedor con clientes asignados | LV (entre filas: no cabe en un CHECK) | D6, R2, R3 |
| `supervisor_id` | `BIGINT UNSIGNED NULL` FK `users.id` `RESTRICT` | obligatorio si vendedor, vacío si no | CK `(rol='vendedor') = (supervisor_id IS NOT NULL)`, LV | R2 |
| | | debe ser un supervisor **activo** (al asignar) | LV (entre filas: no cabe en un CHECK) | R2 |
| `activo` | `BOOLEAN` default `true` | no se borran cuentas; una cuenta desactivada no entra y pierde lo abierto | LV (sin acción de borrar; middleware que cierra la sesión); API (login 403; relee al usuario en cada petición: 401) | R1 |
| `password` | hash bcrypt `$2y$` | solo Laravel lo genera | LV; API solo compara (bcryptjs) | S1 |
| — | — | el administrador no se desactiva ni se quita el rol a sí mismo | LV | L2 |

### productos

| Columna | Tipo | Restricción | Dónde | Regla |
|---------|------|-------------|-------|-------|
| `nombre` | `VARCHAR(120)` | obligatorio y único | UQ, LV | L3 |
| `precio` | `DECIMAL(10,2)` | > 0, máx. 2 decimales | CK `precio > 0`, M (`DECIMAL(10,2)`); **solo la validación de Laravel rechaza más de 2 decimales** (MySQL redondearía `10.555` sin avisar; la API no escribe productos) | R6 |
| `activo` | `BOOLEAN` default `true` | solo activos se agregan; no se borran | LV (sin acción de borrar), API (`INSERT … SELECT … WHERE activo = 1`) | R6 |
| `unidades_por_caja` | `SMALLINT UNSIGNED NULL` (**migración nueva**) | vacío = se vende por unidad; si no, entero 1–999 | CK `unidades_por_caja IS NULL OR unidades_por_caja BETWEEN 1 AND 999`, LV | RP |

### clientes

| Columna | Tipo | Restricción | Dónde | Regla |
|---------|------|-------------|-------|-------|
| `vendedor_id` | `BIGINT UNSIGNED NULL` FK `users.id` | null = disponible | M | R2, R3 |
| | | máx. 10 por vendedor, también con concurrencia | LV + API con TX (bloqueo `SELECT … FOR UPDATE` de la fila del vendedor en `users` antes de contar) | R3, S8 |
| | | tomar es atómico | API: `UPDATE clientes SET vendedor_id=? WHERE id=? AND vendedor_id IS NULL` + `affectedRows` dentro de la TX | R3, S8 |
| | | la lista de disponibles solo muestra id y nombre | API: `SELECT id, nombre FROM clientes WHERE vendedor_id IS NULL` | R3 |
| | | reasignar mueve las oportunidades abiertas | LV en una sola TX: bloquea al vendedor nuevo y cuenta su cartera, `UPDATE clientes.vendedor_id`, `UPDATE oportunidades SET vendedor_id = ? WHERE cliente_id = ? AND etapa NOT IN ('ganada','perdida')` | D2, R3 |
| | | no se libera con oportunidades abiertas | LV en TX con `lockForUpdate()` de la fila del cliente; la API bloquea la misma fila al crear una oportunidad, así que no se cruzan | D3, R5 |
| `nombre` | `VARCHAR(150)` | obligatorio | LV, API | — |
| `nit` | `VARCHAR(12) NULL` | compacto o NULL (CF); único | UQ (NULL no choca), CK `nit IS NULL OR nit REGEXP '^[0-9]{1,11}[0-9K]$'`, LV + API (módulo 11) | R4 |
| `telefono` | `CHAR(8)` | 8 dígitos, inicia 2–9; único | UQ, CK `telefono REGEXP '^[2-9][0-9]{7}$'`, LV + API (normalización) | R4 |
| `correo` | `VARCHAR(150) NULL` | formato válido | LV, API | — |
| — | — | duplicados con concurrencia | UQ es el árbitro final: el error `ER_DUP_ENTRY` se traduce a 409 (API) o a mensaje de campo (LV) | R4, S8 |
| — | — | se elimina solo sin oportunidades | FK `oportunidades.cliente_id RESTRICT` + LV | L4 |

### oportunidades

| Columna | Tipo | Restricción | Dónde | Regla |
|---------|------|-------------|-------|-------|
| `cliente_id` | FK `clientes.id` `RESTRICT` | del cliente de la cartera de quien crea | API (en la TX, `SELECT … FROM clientes WHERE id = ? AND vendedor_id = ? FOR UPDATE`) | R5, R2 |
| `vendedor_id` | FK `users.id` | dueño; sale del token | API (nunca del cuerpo); LV (D2 lo cambia al reasignar) | R2 |
| `titulo` | `VARCHAR(150)` | obligatorio | API | R5 |
| `etapa` | `ENUM(6 etapas)` default `prospecto` | nace en prospecto; transiciones de D1 | M, API | R5, D1 |
| `motivo_perdida` | `VARCHAR(255) NULL` | presente sii `perdida`; 1–255 sin espacios sobrantes | CK `(etapa='perdida') = (motivo_perdida IS NOT NULL)`, CK `motivo_perdida IS NULL OR CHAR_LENGTH(TRIM(motivo_perdida)) > 0`, API | R5 |
| `fecha_cierre` | `DATETIME NULL` | presente sii `ganada` o `perdida` | CK `(etapa IN ('ganada','perdida')) = (fecha_cierre IS NOT NULL)`, API (`UTC_TIMESTAMP()`) | R5 |
| — | — | ganar exige ≥ 1 producto | API (entre tablas; 422 en `etapa`) | R5 |
| — | — | cerrada no cambia (409) | API, dentro de la TX con `FOR UPDATE` | R5 |
| — | — | probabilidad según la etapa | API / LV (se deriva de `etapa` al consultar; no se guarda) | R5, R7 |
| — | — | total = Σ cantidad × precio_unitario | API (SQL `SUM`) / LV (consulta) | R6 |

### oportunidad_productos

| Columna | Tipo | Restricción | Dónde | Regla |
|---------|------|-------------|-------|-------|
| `oportunidad_id` | FK `oportunidades.id` `CASCADE` | al borrar la oportunidad se borran sus líneas | M | R6 |
| `producto_id` | FK `productos.id` `RESTRICT` | solo productos activos; una vez por oportunidad | UQ `(oportunidad_id, producto_id)`, API | R6 |
| `cantidad` | `SMALLINT` | 1–999 | CK `cantidad BETWEEN 1 AND 999`, API | R6 |
| `precio_unitario` | `DECIMAL(10,2)` | copia del catálogo al agregar; > 0 | CK `precio_unitario > 0`, API (`INSERT … SELECT precio FROM productos`) | R6 |
| `unidades_por_caja` | `SMALLINT UNSIGNED NULL` (**migración nueva**) | copia del catálogo al agregar; la cantidad es múltiplo de ella | CK `unidades_por_caja IS NULL OR (unidades_por_caja BETWEEN 1 AND 999 AND cantidad % unidades_por_caja = 0)`, API (se copia en el mismo `INSERT … SELECT`; 422 en `cantidad`) | RP |

### historial_etapas

| Columna | Tipo | Restricción | Dónde | Regla |
|---------|------|-------------|-------|-------|
| `oportunidad_id` | FK `CASCADE` | — | M | R5 |
| `usuario_id` | FK `users.id` | quién hizo el cambio (del token) | API | R5, R2 |
| `etapa_anterior` | `ENUM NULL` | NULL solo en la creación | API | R5 |
| `etapa_nueva` | `ENUM` | obligatoria | M | R5 |
| `created_at` | `DATETIME` | cuándo (UTC) | API | R5, R8 |
| — | — | se escribe en la misma TX que el cambio; un rechazo no deja registro; dos cambios simultáneos no dejan dos registros | API: TX + `SELECT … FOR UPDATE` de la oportunidad, se valida la etapa actual después del bloqueo | R5, S8 |

### actividades

| Columna | Tipo | Restricción | Dónde | Regla |
|---------|------|-------------|-------|-------|
| `cliente_id` | FK `clientes.id` `CASCADE` | al borrar el cliente se borran; la visibilidad sale de la cartera del cliente | M; API (`JOIN clientes … WHERE clientes.vendedor_id = ?`) | R2 |
| `vendedor_id` | FK `users.id` | quién la registró (del token); no cambia al reasignar el cliente, no decide la visibilidad y no se expone | API | R2, D2, R8 |
| `tipo` | `ENUM('llamada','reunion','tarea')` | obligatorio | M, API | — |
| `descripcion` | `VARCHAR(500)` | obligatoria | API | — |
| `vence_en` | `DATETIME NULL` | con zona al recibir; UTC al guardar | API (422 sin zona) | R8 |
| `completada` | `BOOLEAN` default `false` | — | M | — |
| — | — | `vencida` | API (se calcula al responder, no se guarda) | R8 |

### Reglas transversales

| Restricción | Dónde | Regla |
|-------------|-------|-------|
| Rol por puerta | LV (middleware: un vendedor ve un mensaje que lo envía a la SPA); API (login 403 si no es vendedor activo; middleware 401 si ya no lo es) | R1 |
| Cuenta desactivada pierde acceso | LV (middleware en cada petición); API (relee el usuario en cada petición) | R1 |
| Lo ajeno es 404 | API (filtrar por dueño en el `WHERE`, nunca consultar y luego comparar); LV (políticas / `abort(404)`) | R2, S5 |
| Respuestas sin datos internos | API: columnas explícitas en cada `SELECT` (nunca `SELECT *`) y objetos de respuesta armados a mano; nunca `password`, `vendedor_id`, `user_id`, `supervisor_id`. El `cliente: { id, nombre }` de una venta propia es dato de esa venta (aclaración de D2) | R8, S4 |
| Fechas | API: mysql2 con `timezone: 'Z'`, salida con `toISOString()`; 422 si llega sin zona | R8 |
| Reportes por mes de Guatemala | SQL con `DATE_FORMAT(fecha_cierre - INTERVAL 6 HOUR, '%Y-%m')` o rango UTC equivalente (API y LV) | R7 |
| Pronóstico | `ROUND(SUM(total × probabilidad / 100), 2)` sobre abiertas, sin filtro de mes (API y LV) | R7 |
| DECIMAL como número | mysql2 con `decimalNumbers: true` | R6, R7 |

---

## 5. Decisiones de la sección 7

### 5.1 D1 — Transiciones del embudo — **DECIDIDO**

**Se permite:** avanzar una etapa a la vez entre las abiertas
(prospecto → calificado → propuesta → negociacion); ganar **solo** desde negociacion; perder desde
**cualquier etapa abierta**. No hay saltos ni retrocesos. Cualquier otra transición (incluida la que
deja la etapa igual) responde **409**. Una `etapa` del cuerpo que no es una de las 6 responde **422**
en `etapa` (un `?etapa=` de query que no existe es 400; ver 3.3).

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

**Actividades (P-5):** al reasignar un cliente **no se modifica ninguna actividad**. Una actividad es
visible, pendiente o completada, para el vendedor que **hoy** tiene al cliente en su cartera (R2);
`actividades.vendedor_id` sigue diciendo quién la registró, no decide la visibilidad y no se expone.
El vendedor anterior deja de ver las actividades de ese cliente; el nuevo las ve todas y puede
marcarlas (L4.8, A5.12, A5.13, F4.4).

**Aclaración a R8.** El vendedor anterior sigue viendo sus oportunidades cerradas con
`cliente: { id, nombre }`. **El cliente de una venta propia, con id y nombre, es dato de esa venta**, no
un dato de otro vendedor: no se exponen NIT, teléfono, correo ni nada del nuevo vendedor.
**Excepción explícita a A3.3:** en ese caso `GET /clientes/:id` le responde 404 aunque el id aparezca
en su oportunidad.

**Alternativas descartadas:**
- *Todo pasa al nuevo*: cambia reportes ya cerrados y le quita el crédito a quien vendió.
- *Nada se mueve*: el nuevo vendedor no podría trabajar las oportunidades abiertas de su propio cliente (R2).
- *Las actividades pendientes pasan al nuevo* (`vendedor_id` cambia): falsea «quién la registró», que
  es la definición de la columna en el enunciado, y no hace falta porque la visibilidad ya sale de la cartera.
- *Mostrar «registrada por otro vendedor»*: exige exponer un dato de otro vendedor (R8).

### 5.3 D3 — Liberar un cliente con oportunidades abiertas — **DECIDIDO**

**No se puede.** El panel rechaza la liberación con el mensaje «Este cliente tiene oportunidades
abiertas. Reasigne el cliente (sus oportunidades abiertas pasan al nuevo vendedor) o pida al vendedor
que las cierre.» Con oportunidades solo cerradas, sí se libera; las cerradas siguen con su vendedor
original y no las ve quien tome el cliente después (R2).

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

### 5.5 D5 — Campos que el contrato no lista — **DECIDIDO**

**Se ignoran en silencio.** Cada endpoint lee solo los campos que el contrato le lista (lista blanca)
y descarta el resto sin error; esos campos **nunca se usan** (p. ej. `vendedor_id`, `etapa` al crear,
`precio_unitario`). El dueño sigue saliendo del token (R2) y el precio del catálogo (R6).

**Alternativas descartadas:**
- *Rechazarlos con 422* (`errores.campo: "Este campo no se acepta."`): más código y más casos en
  Postman, y una SPA que manda un objeto completo falla sin necesidad.
- *Ignorar en general, pero 422 para campos sensibles*: dos comportamientos que explicar y probar,
  sin ganar seguridad, porque esos campos ya nunca se usan.

### 5.6 Decisiones menores — **DECIDIDAS**

**P-6 — Desmarcar una actividad completada: sí se puede.** `PATCH /actividades/:id` con
`{ "completada": false }` responde 200 y la actividad vuelve a pendiente (`vencida` se recalcula).
*Descartada:* rechazarlo con 409. El contrato no lista 409 para esta ruta, `completada` es un booleano
que admite los dos valores, y un clic equivocado quedaría sin arreglo.

**P-7 — `PATCH /clientes/:id` sin ningún campo editable: 400** («falta el cuerpo»). Aplica a `{}` y a
un cuerpo que, por D5, solo trae campos ignorados (p. ej. `{ "telefno": "55550000" }`): así un error de
escritura no pasa como un 200 que no cambió nada. Si trae al menos un campo editable, los ignorados se
descartan y se responde 200.
*Descartada:* 200 sin cambios. Es idempotente, pero oculta errores del cliente.

**P-8 — `?q=` con NIT escrito con guion o minúscula: se compacta.** El nombre se busca con `q` tal
cual (sin espacios sobrantes); el NIT se compara contra `q` compactado como en R4 (sin espacios ni
guiones, en mayúsculas y sin ceros a la izquierda): `?q=4521817-k` encuentra Comercial El Quetzal.
El panel busca igual (L4.1, L7.2). Si `q` compactado queda vacío, solo se busca por nombre.
*Descartada:* buscar tal cual. Es más simple, pero `4521817-k` no encontraría nada y el vendedor
tendría que saber cómo se guarda el NIT.

### 5.7 D6 — Cambio de rol de una cuenta con equipo o cartera — **DECIDIDO**

No es una pregunta de la sección 7, pero cierra un hueco de R2 y R3: el `CHECK` de `users` no impide
que `clientes.vendedor_id` quede apuntando a una cuenta que ya no es vendedor.

El panel **no permite cambiar el rol** de un supervisor que tiene vendedores a su cargo ni de un
vendedor con clientes asignados. El mensaje pide reasignar primero (L2.12, L2.13). **Desactivar sí se
permite** en los dos casos (D4): el equipo conserva su `supervisor_id` y, al editar a uno de esos
vendedores, L2.3 exige elegir un supervisor activo.

**Alternativas descartadas:**
- *Permitirlo y dejar los datos como estén*: quedan clientes asignados a un administrador o a un
  supervisor, fuera de cualquier cartera (rompe R2 y R3).
- *Bloquear también la desactivación*: choca con D4 (desactivar debe ser inmediato).

---

## 6. Regla propia — **DECIDIDO: venta por caja completa**

*Un producto que se vende por caja o fardo (por ejemplo, gaseosa en lata de 355 ml, caja de 24) solo
se agrega a un pedido en múltiplos de sus unidades por caja: la distribuidora no abre cajas para
despachar unidades sueltas.*

**Columnas nuevas** (cada una en una **migración nueva**, sin editar las del núcleo):

- `productos.unidades_por_caja SMALLINT UNSIGNED NULL`: vacío = se vende por unidad.
- `oportunidad_productos.unidades_por_caja SMALLINT UNSIGNED NULL`: **copia** del catálogo al agregar
  la línea, igual que `precio_unitario` (R6). Si después cambia el catálogo, la línea conserva la regla
  con la que se cotizó.

**Dónde se cumple:**

- **Base:** `CHECK (unidades_por_caja IS NULL OR (unidades_por_caja BETWEEN 1 AND 999 AND cantidad % unidades_por_caja = 0))`
  en `oportunidad_productos`, y `CHECK (unidades_por_caja IS NULL OR unidades_por_caja BETWEEN 1 AND 999)`
  en `productos`. El rango evita la división entre cero (en MySQL, `x % 0` da NULL y el `CHECK` lo dejaría pasar).
  Es un `CHECK` sobre la misma fila: no hace falta trigger.
- **API:** al **agregar** (`POST …/productos`, la copia va en el mismo `INSERT … SELECT`) y al
  **cambiar cantidad** (`PATCH …/productos/:lineaId`, contra la copia de la línea): 422 en `cantidad`
  con «Este producto se vende por caja de N unidades: la cantidad debe ser múltiplo de N.»
- **Panel:** solo administra `productos.unidades_por_caja` en el catálogo (L3), con validación de
  entero 1–999 o vacío. En el panel las oportunidades son de solo lectura, así que no hay segunda
  puerta donde cumplirla.
- **Contrato:** no cambia. Ni `Producto` ni las líneas exponen `unidades_por_caja`; la SPA muestra el
  mensaje del 422 junto a la cantidad.

**Por qué no altera el núcleo:** los 5 productos de `VerificacionSeeder` tienen `unidades_por_caja`
NULL, así que con solo esa semilla toda cantidad de 1 a 999 se acepta y las cifras de control no
cambian. Aunque Alicia le ponga unidades por caja a un producto de la semilla, las líneas existentes
tienen su copia en NULL y no cambian.

**Criterios de aceptación** (salvo RP6, con `migrate:fresh --seed`; `SectorSeeder` incluye al menos
un producto activo con `unidades_por_caja = 24`, llamado aquí «Gaseosa lata 355 ml»):

| # | Dado | Cuando | Entonces |
|---|------|--------|----------|
| RP1 | Una oportunidad abierta de Valeria | `POST …/productos` con Gaseosa lata 355 ml y cantidad 48 | 201; la línea guarda cantidad 48, la copia `unidades_por_caja = 24` y el precio del catálogo |
| RP2 | La misma oportunidad, sin esa línea | `POST …/productos` con cantidad 30 | 422 en `cantidad`; no se crea la línea |
| RP3 | La línea de RP1 | `PATCH` a cantidad 36 | 422 en `cantidad`; a cantidad 72, 200 |
| RP4 | La línea de RP1 | Alicia cambia en el panel `unidades_por_caja` del producto a 12 y luego Valeria hace `PATCH` a 60 | 422 (la línea conserva su copia de 24); una línea nueva del mismo producto en otra oportunidad acepta 60 |
| RP5 | La base migrada | `INSERT` directo en `oportunidad_productos` con `unidades_por_caja = 24` y `cantidad = 30` | MySQL rechaza la fila (CHECK) |
| RP6 | Solo `VerificacionSeeder` | se ejecutan A4.4 y las cifras de control | A4.4 da 201 y las cifras de la sección 11 no cambian |
| RP7 | Alicia en el catálogo | escribe `unidades_por_caja` 0, 1000 o 2.5 | el formulario vuelve con un mensaje junto al campo |

RP1, RP2 y RP3 van en la colección de Postman (A8.2).

**Alternativas descartadas:**

- *Envases retornables* (las recargas de garrafón exigen envases comprados por el cliente): muy propia
  del sector, pero cruza las ventas anteriores del cliente; con un cliente reasignado hay que contar
  envases que vendió otro vendedor sin exponerle esos datos (R8), y no cabe en un `CHECK`.
- *Pedido mínimo para negocios* (el ejemplo del enunciado): es la opción que otra pareja puede elegir, y
  para no romper la semilla (*Ampliación a bodega*, Q2,750.50, con cliente con NIT) habría que limitarla a
  productos marcados, lo que se ve artificial en la defensa. Tampoco cabe en un `CHECK`.
- *Venta al detalle para consumidor final* (máximo de unidades de mayoreo para un cliente CF): el panel
  la puede romper al cambiar el NIT de un cliente con pedidos abiertos, y en la base necesitaría un
  trigger porque un `CHECK` no lee otra tabla.

---

## 7. Control de cambios

| Versión | Etiqueta | Cambio |
|---------|----------|--------|
| 0.1 | — | Borrador inicial |
| 0.2 | — | Revisión: renumera S1–S8 según el enunciado; quita la contraseña pública; decide nombre, vocabulario, regla propia (venta por caja), D6 y mensaje del panel de D3; agrega `ConcurrenciaSeeder` (A3.8); completa criterios L2, L4, L5, L6, A3–A5, A8, F2 y el detalle de endpoints; agrega la columna «Regla» a la sección 4; separa 400 (query) de 422 (cuerpo); decide D5 (ignorar), P-6 (se puede desmarcar), P-7 (400) y P-8 (compactar `q`) |
| 1.0 | `spec-v1` (por crear) | Cierra P-5 (las actividades no se tocan al reasignar; visibilidad por cartera actual), aclaración a R8 y excepción a A3.3 en D2; criterios A5.12–A5.14, A6.6, F4.4, F5.3; D3 solo con mensaje del panel. Sin pendientes |
| 1.1 | — (`spec-v1` no se mueve) | Aclaración en 3.4: cuándo se quita el prefijo `+502`/`502` del teléfono (solo si empieza con `+502`, o si quedan exactamente 11 dígitos que empiezan con 502), con ejemplos. No cambia ningún criterio de la semilla |
