// A3.8, A3.9 y S8 de la puerta API. Solo usa fetch nativo y exige crm_agua_test.
// Restaure la base antes y después con VerificacionSeeder; ConcurrenciaSeeder solo se usa en A3.8.

const API = process.env.API_URL ?? 'http://127.0.0.1:3000/api';
const CLAVE = process.env.CLAVE_SEMILLA;
const BASE = process.env.DB_DATABASE;

if (BASE !== 'crm_agua_test') {
  throw new Error('Estas pruebas solo pueden ejecutarse con DB_DATABASE=crm_agua_test.');
}
if (!CLAVE) {
  throw new Error('Defina CLAVE_SEMILLA en el entorno; no se guarda ninguna credencial en el script.');
}

async function pedir(metodo, ruta, token, cuerpo) {
  const respuesta = await fetch(`${API}${ruta}`, {
    method: metodo,
    headers: {
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(cuerpo === undefined ? {} : { 'Content-Type': 'application/json' }),
    },
    body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo),
  });
  const texto = await respuesta.text();
  return { estado: respuesta.status, cuerpo: texto === '' ? null : JSON.parse(texto) };
}

async function entrar(correo) {
  const respuesta = await pedir('POST', '/auth/login', null, { correo, clave: CLAVE });
  if (respuesta.estado !== 200) throw new Error(`Login falló para ${correo}: ${respuesta.estado}`);
  return respuesta.cuerpo.token;
}

function conteo(resultados) {
  return resultados.reduce((salida, resultado) => {
    salida[resultado.estado] = (salida[resultado.estado] ?? 0) + 1;
    return salida;
  }, {});
}

function comprobar(nombre, resultados, esperado) {
  const obtenido = conteo(resultados);
  const correcto = JSON.stringify(obtenido) === JSON.stringify(esperado);
  console.log(`${nombre}: ${JSON.stringify(obtenido)} ${correcto ? 'OK' : 'FALLA'}`);
  if (!correcto) process.exitCode = 1;
}

function comprobarA39(resultados, token) {
  const obtenido = conteo(resultados);
  const exitos = (obtenido[200] ?? 0) + (obtenido[201] ?? 0);
  const correcto = exitos === 1 && (obtenido[409] ?? 0) === 2;
  console.log(`A3.9 dos tomas + un alta de Carlos: ${JSON.stringify(obtenido)} ${correcto ? 'OK' : 'FALLA'}`);
  if (!correcto) process.exitCode = 1;
  return pedir('GET', '/clientes', token).then((respuesta) => {
    const cartera = respuesta.cuerpo.length;
    const carteraCorrecta = cartera === 10;
    console.log(`A3.9 cartera final de Carlos: ${cartera}/10 ${carteraCorrecta ? 'OK' : 'FALLA'}`);
    if (!carteraCorrecta) process.exitCode = 1;
  });
}

const carlos = await entrar('carlos@crm.test');
const disponibles = (await pedir('GET', '/clientes/disponibles', carlos)).cuerpo;
const paraCarlos = disponibles.filter((cliente) => cliente.nombre !== 'Gimnasio Fuerza').slice(0, 2);
const alta = pedir('POST', '/clientes', carlos, {
  nombre: `Alta concurrente ${Date.now()}`,
  nit: 'CF',
  telefono: `9${String(Math.floor(Math.random() * 1e7)).padStart(7, '0')}`,
});
const tomas = paraCarlos.map((cliente) => pedir('POST', `/clientes/${cliente.id}/tomar`, carlos));
const resultadosA39 = await Promise.all([...tomas, alta]);
await comprobarA39(resultadosA39, carlos);

const vendedores = await Promise.all(
  Array.from({ length: 10 }, (_, indice) => entrar(`concurrencia${String(indice + 1).padStart(2, '0')}@crm.test`)),
);
const disponiblesDespues = (await pedir('GET', '/clientes/disponibles', carlos)).cuerpo;
const objetivo = disponiblesDespues.find((cliente) => cliente.nombre === 'Gimnasio Fuerza') ?? disponiblesDespues[0];
const resultadosA38 = await Promise.all(
  vendedores.map((token) => pedir('POST', `/clientes/${objetivo.id}/tomar`, token)),
);
comprobar('A3.8 diez tomas del mismo cliente', resultadosA38, { 200: 1, 409: 9 });

const valeria = await entrar('valeria@crm.test');
const nitDuplicado = '123456789K';
const resultadosNit = await Promise.all(Array.from({ length: 2 }, (_, indice) => pedir('POST', '/clientes', valeria, {
  nombre: `NIT concurrente ${indice}`,
  nit: nitDuplicado,
  telefono: `2${String(1000000 + indice).padStart(7, '0')}`,
})));
comprobar('S8 dos altas con el mismo NIT', resultadosNit, { 201: 1, 409: 1 });

console.log(process.exitCode ? 'Se detectaron fallas.' : 'Pruebas de concurrencia correctas.');
