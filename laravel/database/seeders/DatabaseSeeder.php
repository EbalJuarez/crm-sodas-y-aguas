<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * php artisan migrate:fresh --seed  → verificación + sector (L1.5).
 * php artisan migrate:fresh --seeder=VerificacionSeeder  → solo verificación (defensa).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            VerificacionSeeder::class,
            SectorSeeder::class,
        ]);
    }
}
