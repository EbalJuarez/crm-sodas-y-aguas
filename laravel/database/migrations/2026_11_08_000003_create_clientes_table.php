<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes (R3, R4).
 * - vendedor_id NULL = disponible. El máximo de 10 cruza filas: lo hacen Laravel y la API
 *   con un bloqueo de la fila del vendedor.
 * - nit compacto (2 a 12 caracteres) o NULL para CF: el índice único deja varios NULL.
 * - Los índices únicos de nit y telefono son el árbitro final de los duplicados (S8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->string('nombre', 150);
            $table->string('nit', 12)->nullable()->unique();
            $table->char('telefono', 8)->unique();
            $table->string('correo', 150)->nullable();
            $table->datetimes();
        });

        // REGEXP_LIKE con 'c' distingue mayúsculas: la colación utf8mb4_unicode_ci aceptaría una «k».
        DB::statement(
            "ALTER TABLE clientes ADD CONSTRAINT clientes_nit_compacto
             CHECK (nit IS NULL OR REGEXP_LIKE(nit, '^[0-9]{1,11}[0-9K]$', 'c'))"
        );
        DB::statement(
            "ALTER TABLE clientes ADD CONSTRAINT clientes_telefono_formato
             CHECK (REGEXP_LIKE(telefono, '^[2-9][0-9]{7}$', 'c'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
