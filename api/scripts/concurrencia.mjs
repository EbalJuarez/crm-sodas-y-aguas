// Pruebas de concurrencia de A3 y A4 (R3, R4, R5, S8). Solo fetch nativo y Promise.all; sin dependencias.
//
// Requisitos: la API en marcha y la base recién cargada con la semilla de verificación
// (Carlos con 9 de 10 y 6 clientes disponibles). La contraseña de las cuentas de la semilla
// no se escribe aquí (CLAUDE.md): se pasa en la variable CLAVE_SEMILLA (está en el README).
//
//   PowerShell:  $env:CLAVE_SEMILLA = '<contraseña del README>'; node scripts/concurrencia.mjs
//   Otra URL:    $env:API_URL = 'http://localhost:3000/api'
//
// El script modifica la base. Para restaurarla (desde laravel/):
//   php artisan migrate:fresh --seeder=VerificacionSeeder
//
// Orden: (b) va antes que (a). Si (a) corriera primero y Carlos ganara la toma, llegaría a 10
// y en (b) ya no podría haber ningún 200. Así, en (a) las tomas de Carlos dan 409 por cartera llena.

const API = process.env.API_URL ?? 'http://localhost:3000/api';
const CLAVE = process.env.CLAVE_SEMILLA;

if (!CLAVE) {
  console.error('Defina CLAVE_SEMILLA con la contraseña de las semillas (ver README).');
  process.exit(2);
}

async function peticion(metodo, ruta, token, cuerpo) {
  const respuesta = await fetch(`${API}${ruta}`, {
    method: metodo,
    headers: {
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(cuerpo !== undefined ? { 'Content-Type': 'application/json' } : {}),
    },
    body: cuerpo !== undefined ? JSON.stringify(cuerpo) : undefined,
  });
  const texto = await respuesta.text();

  return { estado: respuesta.status, cuerpo: texto ? JSON.parse(texto) : null };
}

async function entrar(correo) {
  const { estado, cuerpo } = await peticion('POST', '/auth/login', null, { correo, clave: CLAVE });
  if (estado !== 200) {
    throw new Error(`No se pudo iniciar sesión con ${correo}: ${estado} ${JSON.stringify(cuerpo)}`);
  }

  return cuerpo.token;
}

function contar(resultados) {
  const conteo = {};
  for (const { estado } of resultados) {
    conteo[estado] = (conteo[estado] ?? 0) + 1;
  }

  return conteo;
}

let fallas = 0;

function informar(titulo, resultados, esperado) {
  const conteo = contar(resultados);
  const correcto = Object.keys(esperado).length === Object.keys(conteo).length
    && Object.entries(esperado).every(([estado, cantidad]) => conteo[estado] === cantidad);
  if (!correcto) {
    fallas += 1;
  }

  console.log(`\n${titulo}`);
  console.log(`  obtenido: ${JSON.stringify(conteo)}  esperado: ${JSON.stringify(esperado)}  → ${correcto ? 'OK' : 'FALLA'}`);
  for (const { quien, estado, cuerpo } of resultados) {
    const detalle = cuerpo?.error ?? (cuerpo?.nombre ? `${cuerpo.nombre} (id ${cuerpo.id})` : '');
    const etapa = cuerpo?.etapa ? `etapa ${cuerpo.etapa}` : '';
    console.log(`    ${quien.padEnd(8)} ${estado}  ${detalle || etapa}`);
  }
}

const tokens = {
  Valeria: await entrar('valeria@crm.test'),
  Diego: await entrar('diego@crm.test'),
  Carlos: await entrar('carlos@crm.test'),
};

const carteraCarlos = (await peticion('GET', '/clientes', tokens.Carlos)).cuerpo.length;
let disponibles = (await peticion('GET', '/clientes/disponibles', tokens.Valeria)).cuerpo;
console.log(`Carlos tiene ${carteraCarlos} de 10; hay ${disponibles.length} clientes disponibles.`);

if (carteraCarlos !== 9 || disponibles.length < 6) {
  console.error('Estado inesperado: restaure la base con php artisan migrate:fresh --seeder=VerificacionSeeder');
  process.exit(2);
}

// (b) Carlos (9 de 10) toma a la vez 5 clientes distintos → exactamente un 200 y cuatro 409.
const paraCarlos = disponibles.slice(0, 5);
const resultadosB = await Promise.all(paraCarlos.map(async (cliente) => ({
  quien: 'Carlos',
  ...(await peticion('POST', `/clientes/${cliente.id}/tomar`, tokens.Carlos)),
})));
informar('(b) 5 tomas simultáneas de clientes distintos por Carlos (9 de 10)', resultadosB, { 200: 1, 409: 4 });

// (a) 10 tomas simultáneas del mismo disponible entre Valeria, Diego y Carlos → un 200 y nueve 409.
disponibles = (await peticion('GET', '/clientes/disponibles', tokens.Valeria)).cuerpo;
const objetivo = disponibles[0];
const turnos = Array.from({ length: 10 }, (_, indice) => ['Valeria', 'Diego', 'Carlos'][indice % 3]);
const resultadosA = await Promise.all(turnos.map(async (quien) => ({
  quien,
  ...(await peticion('POST', `/clientes/${objetivo.id}/tomar`, tokens[quien])),
})));
informar(`(a) 10 tomas simultáneas de «${objetivo.nombre}» entre Valeria, Diego y Carlos`, resultadosA, { 200: 1, 409: 9 });

// (c) 5 altas simultáneas de Valeria con el mismo teléfono → un 201 y cuatro 409 (índice único).
// El teléfono cambia en cada corrida para que el script se pueda repetir sin restaurar.
const telefono = `9${String(Math.floor(Math.random() * 1e7)).padStart(7, '0')}`;
const resultadosC = await Promise.all(Array.from({ length: 5 }, async (_, indice) => ({
  quien: 'Valeria',
  ...(await peticion('POST', '/clientes', tokens.Valeria, {
    nombre: `Prueba de concurrencia ${indice + 1}`,
    nit: 'CF',
    telefono,
  })),
})));
informar(`(c) 5 altas simultáneas con el teléfono ${telefono}`, resultadosC, { 201: 1, 409: 4 });

// (d) A4.14: dos PATCH /etapa simultáneos (prospecto → calificado) sobre una oportunidad nueva
// de Valeria → un 200 y un 409, y un solo registro nuevo en el historial.
const clienteDeValeria = (await peticion('GET', '/clientes', tokens.Valeria)).cuerpo[0];
const nueva = await peticion('POST', '/oportunidades', tokens.Valeria, {
  cliente_id: clienteDeValeria.id,
  titulo: `Prueba de concurrencia de etapa ${Date.now()}`,
});
if (nueva.estado !== 201) {
  console.error(`No se pudo crear la oportunidad de prueba: ${nueva.estado} ${JSON.stringify(nueva.cuerpo)}`);
  process.exit(2);
}
const resultadosD = await Promise.all([1, 2].map(async () => ({
  quien: 'Valeria',
  ...(await peticion('PATCH', `/oportunidades/${nueva.cuerpo.id}/etapa`, tokens.Valeria, { etapa: 'calificado' })),
})));
informar(`(d) 2 cambios de etapa simultáneos de «${nueva.cuerpo.titulo}»`, resultadosD, { 200: 1, 409: 1 });

const { historial } = (await peticion('GET', `/oportunidades/${nueva.cuerpo.id}`, tokens.Valeria)).cuerpo;
console.log(`  historial (${historial.length} registros; se esperan 2):`);
for (const registro of historial) {
  console.log(`    ${String(registro.etapa_anterior).padEnd(10)} → ${registro.etapa_nueva.padEnd(10)} ${registro.usuario}  ${registro.fecha}`);
}
if (historial.length !== 2) {
  fallas += 1;
  console.log('  → FALLA: el historial no tiene exactamente 2 registros');
}

console.log(`\n${fallas === 0 ? 'Todas las pruebas dieron lo esperado.' : `${fallas} prueba(s) con FALLA.`}`);
console.log('Para restaurar la base (desde laravel/): php artisan migrate:fresh --seeder=VerificacionSeeder');
process.exit(fallas === 0 ? 0 : 1);
