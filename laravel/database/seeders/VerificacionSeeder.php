<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Semilla de verificación: EXACTAMENTE la sección 11 del enunciado.
 * No se "mejora" ni se ajusta: las cifras de control y las pruebas del catedrático dependen de ella.
 * Todas las fechas están en UTC.
 *
 * Fuera de la sección 11 solo se agregan columnas técnicas de Laravel: created_at/updated_at,
 * y email_verified_at para que Breeze deje entrar a las cuentas.
 */
class VerificacionSeeder extends Seeder
{
    /** Fecha de los registros que siguen abiertos (sugerencia del enunciado). */
    private const ULTIMO_REGISTRO_ABIERTA = '2026-10-02 16:00:00';

    private const ETAPAS_ABIERTAS = ['prospecto', 'calificado', 'propuesta', 'negociacion'];

    public function run(): void
    {
        $ahora = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');

        DB::transaction(function () use ($ahora) {
            $usuarios = $this->cuentas($ahora);
            $productos = $this->productos($ahora);
            $clientes = $this->clientes($usuarios, $ahora);
            $this->oportunidades($usuarios, $productos, $clientes);
            $this->actividades($usuarios, $clientes, $ahora);
        });
    }

    /** @return array<string,int> nombre corto => id */
    private function cuentas(string $ahora): array
    {
        // La contraseña pública de las semillas (sección 11). Solo Laravel genera el hash.
        $hash = Hash::make('Ventas2026');

        $cuentas = [
            ['Alicia',  'Alicia Morales', 'admin@crm.test',   'administrador', null,     true],
            ['Sergio',  'Sergio Ruiz',    'sergio@crm.test',  'supervisor',    null,     true],
            ['Sandra',  'Sandra Pérez',   'sandra@crm.test',  'supervisor',    null,     true],
            ['Valeria', 'Valeria López',  'valeria@crm.test', 'vendedor',      'Sergio', true],
            ['Diego',   'Diego Castillo', 'diego@crm.test',   'vendedor',      'Sergio', true],
            ['Carlos',  'Carlos Ajú',     'carlos@crm.test',  'vendedor',      'Sandra', true],
            ['Ivan',    'Iván Toj',       'ivan@crm.test',    'vendedor',      'Sandra', false],
        ];

        $ids = [];
        foreach ($cuentas as [$corto, $nombre, $correo, $rol, $supervisor, $activo]) {
            $ids[$corto] = DB::table('users')->insertGetId([
                'name' => $nombre,
                'email' => $correo,
                'email_verified_at' => $ahora,
                'password' => $hash,
                'rol' => $rol,
                'supervisor_id' => $supervisor === null ? null : $ids[$supervisor],
                'activo' => $activo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        return $ids;
    }

    /** @return array<string,int> nombre => id */
    private function productos(string $ahora): array
    {
        $productos = [
            ['Plan básico',       '1200.00', true],
            ['Plan avanzado',     '2750.50', true],
            ['Instalación',       '450.00',  true],
            ['Soporte anual',     '3600.00', true],
            ['Licencia anterior', '999.99',  false],
        ];

        $ids = [];
        foreach ($productos as [$nombre, $precio, $activo]) {
            $ids[$nombre] = DB::table('productos')->insertGetId([
                'nombre' => $nombre,
                'precio' => $precio,
                'activo' => $activo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        return $ids;
    }

    /**
     * NIT compacto, o null para CF (consumidor final).
     *
     * @return array<string,int> nombre => id
     */
    private function clientes(array $usuarios, string $ahora): array
    {
        $clientes = [
            // vendedor,  cliente,                    nit,        teléfono,   correo
            ['Valeria', 'Comercial El Quetzal',      '4521817K', '55551234', 'compras@elquetzal.test'],
            ['Valeria', 'Ferretería Xela',           '98765434', '77612345', null],
            ['Valeria', 'María Coyoy',               null,       '41234567', null],
            ['Diego',   'Farmacia Los Altos',        '34567895', '77634567', 'gerencia@losaltos.test'],
            ['Diego',   'Luis Tzul',                 null,       '52345678', null],
            ['Carlos',  'Abarrotes San Juan',        '12345679', '77651001', null],
            ['Carlos',  'Panadería La Esperanza',    null,       '30001002', null],
            ['Carlos',  'Taller El Volcán',          '22233342', '77651003', null],
            ['Carlos',  'Ana Xicará',                null,       '30001004', null],
            ['Carlos',  'Librería Cantel',           '55566677', '77651005', null],
            ['Carlos',  'Pedro Batz',                null,       '30001006', null],
            ['Carlos',  'Tienda La Bendición',       null,       '30001007', null],
            ['Carlos',  'Distribuidora Occidente',   '77788893', '77651008', null],
            ['Carlos',  'Rosa Tax',                  null,       '30001009', null],
            ['Ivan',    'Hotel Los Pinos',           '66655544', '77661001', null],
            [null,      'Agencia Cuatro Caminos',    null,       '30002001', null],
            [null,      'Café Zunil',                null,       '30002002', null],
            [null,      'Clínica San Rafael',        '8889990K', '77672003', null],
            [null,      'Constructora Altiplano',    '44433328', '77672004', null],
            [null,      'Escuela Futuro',            null,       '30002005', null],
            [null,      'Gimnasio Fuerza',           null,       '30002006', null],
        ];

        $ids = [];
        foreach ($clientes as [$vendedor, $nombre, $nit, $telefono, $correo]) {
            $ids[$nombre] = DB::table('clientes')->insertGetId([
                'vendedor_id' => $vendedor === null ? null : $usuarios[$vendedor],
                'nombre' => $nombre,
                'nit' => $nit,
                'telefono' => $telefono,
                'correo' => $correo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        return $ids;
    }

    private function oportunidades(array $usuarios, array $productos, array $clientes): void
    {
        $oportunidades = [
            // título, cliente, vendedor, etapa, cierre UTC, motivo, perdida desde, líneas [producto, cantidad, precio guardado]
            ['Renovación de planes', 'Comercial El Quetzal', 'Valeria', 'ganada', '2026-10-05 16:00:00', null, null,
                [['Plan avanzado', 2, '2600.00'], ['Instalación', 1, '450.00']]],
            ['Equipo para sucursal', 'Ferretería Xela', 'Valeria', 'ganada', '2026-11-01 03:30:00', null, null,
                [['Plan básico', 3, '1200.00']]],
            ['Soporte del hogar', 'María Coyoy', 'Valeria', 'ganada', '2026-11-01 06:30:00', null, null,
                [['Soporte anual', 1, '3600.00']]],
            ['Ampliación a bodega', 'Comercial El Quetzal', 'Valeria', 'negociacion', null, null, null,
                [['Plan avanzado', 1, '2750.50']]],
            ['Capacitación de personal', 'Ferretería Xela', 'Valeria', 'propuesta', null, null, null,
                [['Instalación', 2, '450.00']]],
            ['Plan familiar', 'María Coyoy', 'Valeria', 'perdida', '2026-10-20 18:00:00', 'Eligió a la competencia', 'propuesta',
                [['Plan básico', 1, '1200.00'], ['Licencia anterior', 1, '999.99']]],
            ['Seguimiento anual', 'Comercial El Quetzal', 'Valeria', 'prospecto', null, null, null,
                []],
            ['Licencias de caja', 'Farmacia Los Altos', 'Diego', 'ganada', '2026-10-10 15:00:00', null, null,
                [['Plan básico', 2, '1200.00'], ['Soporte anual', 1, '3600.00']]],
            ['Mantenimiento', 'Luis Tzul', 'Diego', 'calificado', null, null, null,
                [['Instalación', 1, '450.00']]],
            ['Plan anual', 'Abarrotes San Juan', 'Carlos', 'ganada', '2026-10-15 20:00:00', null, null,
                [['Soporte anual', 2, '3600.00']]],
            ['Cotización de equipo', 'Taller El Volcán', 'Carlos', 'negociacion', null, null, null,
                [['Plan avanzado', 2, '2750.50']]],
        ];

        foreach ($oportunidades as [$titulo, $cliente, $vendedor, $etapa, $cierre, $motivo, $perdidaDesde, $lineas]) {
            $recorrido = $this->recorrido($etapa, $perdidaDesde);
            $fechas = $this->fechasDelHistorial(count($recorrido), $cierre ?? self::ULTIMO_REGISTRO_ABIERTA);

            $oportunidadId = DB::table('oportunidades')->insertGetId([
                'cliente_id' => $clientes[$cliente],
                'vendedor_id' => $usuarios[$vendedor],
                'titulo' => $titulo,
                'etapa' => $etapa,
                'motivo_perdida' => $motivo,
                'fecha_cierre' => $cierre,
                'created_at' => $fechas[0],
                'updated_at' => end($fechas),
            ]);

            foreach ($lineas as [$producto, $cantidad, $precio]) {
                DB::table('oportunidad_productos')->insert([
                    'oportunidad_id' => $oportunidadId,
                    'producto_id' => $productos[$producto],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'created_at' => $fechas[0],
                    'updated_at' => $fechas[0],
                ]);
            }

            // Un registro por cada etapa recorrida, hechos por su vendedor.
            $anterior = null;
            foreach ($recorrido as $i => $etapaNueva) {
                DB::table('historial_etapas')->insert([
                    'oportunidad_id' => $oportunidadId,
                    'usuario_id' => $usuarios[$vendedor],
                    'etapa_anterior' => $anterior,
                    'etapa_nueva' => $etapaNueva,
                    'created_at' => $fechas[$i],
                ]);
                $anterior = $etapaNueva;
            }
        }
    }

    /**
     * Etapas que recorrió la oportunidad, desde la creación (prospecto) hasta la actual.
     * Las ganadas pasaron por las cuatro abiertas; una perdida, por las abiertas hasta $perdidaDesde.
     *
     * @return list<string>
     */
    private function recorrido(string $etapa, ?string $perdidaDesde): array
    {
        if ($etapa === 'ganada') {
            return [...self::ETAPAS_ABIERTAS, 'ganada'];
        }

        if ($etapa === 'perdida') {
            $hasta = array_search($perdidaDesde, self::ETAPAS_ABIERTAS, true);

            return [...array_slice(self::ETAPAS_ABIERTAS, 0, $hasta + 1), 'perdida'];
        }

        $hasta = array_search($etapa, self::ETAPAS_ABIERTAS, true);

        return array_slice(self::ETAPAS_ABIERTAS, 0, $hasta + 1);
    }

    /**
     * Sugerencia del enunciado: tres días entre registros, terminando en la fecha de cierre
     * (o el 2 de octubre de 2026 a las 16:00 UTC si sigue abierta).
     *
     * @return list<string>
     */
    private function fechasDelHistorial(int $cantidad, string $ultima): array
    {
        $fin = CarbonImmutable::parse($ultima, 'UTC');
        $fechas = [];
        for ($i = $cantidad - 1; $i >= 0; $i--) {
            $fechas[] = $fin->subDays(3 * $i)->format('Y-m-d H:i:s');
        }

        return $fechas;
    }

    private function actividades(array $usuarios, array $clientes, string $ahora): void
    {
        $actividades = [
            ['Comercial El Quetzal', 'Valeria', 'llamada', 'Confirmar fecha de instalación',   '2026-10-08 15:00:00', true],
            ['Ferretería Xela',      'Valeria', 'tarea',   'Enviar propuesta de capacitación', '2030-01-15 15:00:00', false],
            ['María Coyoy',          'Valeria', 'reunion', 'Presentar opciones de soporte',    '2026-09-01 15:00:00', false],
            ['Farmacia Los Altos',   'Diego',   'llamada', 'Renovación de licencias',          '2030-02-01 15:00:00', false],
        ];

        foreach ($actividades as [$cliente, $vendedor, $tipo, $descripcion, $venceEn, $completada]) {
            DB::table('actividades')->insert([
                'cliente_id' => $clientes[$cliente],
                'vendedor_id' => $usuarios[$vendedor],
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'vence_en' => $venceEn,
                'completada' => $completada,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }
}
