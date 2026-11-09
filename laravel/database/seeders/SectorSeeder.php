<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos demostrativos del sector. Corre después de VerificacionSeeder y no reutiliza
 * correos, NIT, teléfonos ni nombres de productos de la semilla de verificación.
 */
class SectorSeeder extends Seeder
{
    public function run(): void
    {
        $ahora = CarbonImmutable::parse('2026-11-08 16:00:00', 'UTC')->format('Y-m-d H:i:s');

        DB::transaction(function () use ($ahora): void {
            $usuarios = $this->cuentas($ahora);
            $productos = $this->productos($ahora);
            $clientes = $this->clientes($usuarios, $ahora);

            $this->oportunidades($usuarios, $productos, $clientes);
            $this->actividades($usuarios, $clientes, $ahora);
        });
    }

    /** @return array<string, int> */
    private function cuentas(string $ahora): array
    {
        $hash = Hash::make('Ventas2026');
        $supervisor = DB::table('users')->insertGetId([
            'name' => 'Marta Cifuentes',
            'email' => 'marta@aguaviva.test',
            'email_verified_at' => $ahora,
            'password' => $hash,
            'rol' => 'supervisor',
            'supervisor_id' => null,
            'activo' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $ids = ['Marta' => $supervisor];
        foreach ([
            ['Julia', 'Julia Méndez', 'julia@aguaviva.test'],
            ['Andrés', 'Andrés Choc', 'andres@aguaviva.test'],
        ] as [$corto, $nombre, $correo]) {
            $ids[$corto] = DB::table('users')->insertGetId([
                'name' => $nombre,
                'email' => $correo,
                'email_verified_at' => $ahora,
                'password' => $hash,
                'rol' => 'vendedor',
                'supervisor_id' => $supervisor,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function productos(string $ahora): array
    {
        $productos = [
            ['Recarga de garrafón 5 galones', '28.00', null],
            ['Envase de garrafón retornable', '85.00', null],
            ['Fardo de botellas 600 ml', '42.00', 24],
            ['Caja de gaseosas surtidas', '96.00', 12],
            ['Dispensador de agua', '325.00', null],
            ['Servicio de entrega programada', '150.00', null],
        ];

        $ids = [];
        foreach ($productos as [$nombre, $precio, $unidades]) {
            $ids[$nombre] = DB::table('productos')->insertGetId([
                'nombre' => $nombre,
                'precio' => $precio,
                'unidades_por_caja' => $unidades,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function clientes(array $usuarios, string $ahora): array
    {
        $clientes = [
            ['Julia', 'Tienda El Manantial', '1456789K', '23881234', 'compras@manantial.test'],
            ['Julia', 'Restaurante La Cumbre', '25789430', '23885678', null],
            ['Julia', 'Hogar López', null, '41239876', null],
            ['Andrés', 'Colegio Los Pinos', '36549878', '77681234', 'administracion@lospinos.test'],
            ['Andrés', 'Oficinas del Valle', '47896523', '77684567', null],
            ['Andrés', 'Hogar Choc', null, '52349876', null],
        ];

        $ids = [];
        foreach ($clientes as [$vendedor, $nombre, $nit, $telefono, $correo]) {
            $ids[$nombre] = DB::table('clientes')->insertGetId([
                'vendedor_id' => $usuarios[$vendedor],
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
            [
                'Tienda El Manantial', 'Julia', 'Abastecimiento semanal de bebidas', 'ganada',
                '2026-10-28 15:00:00', null, [
                    ['Fardo de botellas 600 ml', 48, '42.00', 24],
                    ['Caja de gaseosas surtidas', 24, '96.00', 12],
                ],
            ],
            [
                'Colegio Los Pinos', 'Andrés', 'Instalación de dispensadores', 'propuesta',
                null, null, [
                    ['Dispensador de agua', 2, '325.00', null],
                    ['Servicio de entrega programada', 1, '150.00', null],
                ],
            ],
            ['Restaurante La Cumbre', 'Julia', 'Recarga para cocina', 'prospecto', null, null, []],
            ['Oficinas del Valle', 'Andrés', 'Abastecimiento corporativo', 'calificado', null, null, [
                ['Envase de garrafón retornable', 4, '85.00', null],
            ]],
            ['Hogar López', 'Julia', 'Ruta de garrafones', 'negociacion', null, null, [
                ['Recarga de garrafón 5 galones', 6, '28.00', null],
            ]],
            ['Hogar Choc', 'Andrés', 'Entrega de prueba', 'perdida', '2026-10-20 15:00:00', 'Eligió comprar en otro lugar', [
                ['Recarga de garrafón 5 galones', 2, '28.00', null],
            ]],
        ];

        foreach ($oportunidades as [$cliente, $vendedor, $titulo, $etapa, $cierre, $motivo, $lineas]) {
            $creada = $cierre ?? '2026-11-02 16:00:00';
            $oportunidadId = DB::table('oportunidades')->insertGetId([
                'cliente_id' => $clientes[$cliente],
                'vendedor_id' => $usuarios[$vendedor],
                'titulo' => $titulo,
                'etapa' => $etapa,
                'motivo_perdida' => $motivo,
                'fecha_cierre' => $cierre,
                'created_at' => $creada,
                'updated_at' => $creada,
            ]);

            foreach ($lineas as [$producto, $cantidad, $precio, $unidades]) {
                DB::table('oportunidad_productos')->insert([
                    'oportunidad_id' => $oportunidadId,
                    'producto_id' => $productos[$producto],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'unidades_por_caja' => $unidades,
                    'created_at' => $creada,
                    'updated_at' => $creada,
                ]);
            }

            DB::table('historial_etapas')->insert([
                'oportunidad_id' => $oportunidadId,
                'usuario_id' => $usuarios[$vendedor],
                'etapa_anterior' => null,
                'etapa_nueva' => 'prospecto',
                'created_at' => $creada,
            ]);

            if ($etapa !== 'prospecto') {
                DB::table('historial_etapas')->insert([
                    'oportunidad_id' => $oportunidadId,
                    'usuario_id' => $usuarios[$vendedor],
                    'etapa_anterior' => 'prospecto',
                    'etapa_nueva' => $etapa,
                    'created_at' => $creada,
                ]);
            }
        }
    }

    private function actividades(array $usuarios, array $clientes, string $ahora): void
    {
        foreach ([
            ['Tienda El Manantial', 'Julia', 'llamada', 'Confirmar ruta de entrega semanal', '2026-11-12 15:00:00', false],
            ['Restaurante La Cumbre', 'Julia', 'reunion', 'Revisar consumo de garrafones', '2026-11-14 16:00:00', false],
            ['Colegio Los Pinos', 'Andrés', 'tarea', 'Enviar propuesta de dispensadores', '2026-11-10 17:00:00', false],
            ['Hogar Choc', 'Andrés', 'llamada', 'Confirmar recarga de garrafón', '2026-10-30 15:00:00', true],
        ] as [$cliente, $vendedor, $tipo, $descripcion, $venceEn, $completada]) {
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
