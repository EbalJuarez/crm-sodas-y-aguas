import mysql from 'mysql2/promise';

const pool = mysql.createPool({
  host: process.env.DB_HOST,
  port: Number.parseInt(process.env.DB_PORT ?? '3306', 10),
  database: process.env.DB_DATABASE,
  user: process.env.DB_USERNAME,
  password: process.env.DB_PASSWORD,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  decimalNumbers: true,
  timezone: 'Z',
});

/**
 * Ejecuta `trabajo(conexion)` dentro de una transacción: commit si termina, rollback si lanza.
 * La conexión se libera siempre (finally), también si el rollback falla.
 */
export async function transaccion(trabajo) {
  const conexion = await pool.getConnection();

  try {
    await conexion.beginTransaction();
    const resultado = await trabajo(conexion);
    await conexion.commit();

    return resultado;
  } catch (error) {
    try {
      await conexion.rollback();
    } catch (errorRollback) {
      console.error(errorRollback);
    }

    throw error;
  } finally {
    conexion.release();
  }
}

export default pool;
