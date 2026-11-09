<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Datos auxiliares exclusivos de A3.8; nunca se ejecuta desde DatabaseSeeder. */
class ConcurrenciaSeeder extends Seeder
{
    public function run(): void
    {
        $ahora = now('UTC');
        $supervisorId = DB::table('users')->where('email', 'sergio@crm.test')->value('id');

        if ($supervisorId === null) {
            throw new \RuntimeException('Cargue VerificacionSeeder antes de ConcurrenciaSeeder.');
        }

        $hash = Hash::make('Ventas2026');
        $cuentas = [];
        for ($indice = 1; $indice <= 10; $indice++) {
            $numero = str_pad((string) $indice, 2, '0', STR_PAD_LEFT);
            $cuentas[] = [
                'name' => "Vendedor de concurrencia {$numero}",
                'email' => "concurrencia{$numero}@crm.test",
                'password' => $hash,
                'rol' => 'vendedor',
                'supervisor_id' => $supervisorId,
                'activo' => true,
                'email_verified_at' => $ahora,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::table('users')->insert($cuentas);
    }
}
