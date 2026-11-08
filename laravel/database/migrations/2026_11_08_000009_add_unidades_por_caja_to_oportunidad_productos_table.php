<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regla propia (spec, sección 6): la línea copia unidades_por_caja del catálogo al agregarse,
 * como precio_unitario (R6), y la cantidad debe ser múltiplo de esa copia.
 * Las líneas existentes quedan en NULL: la regla no se les aplica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oportunidad_productos', function (Blueprint $table) {
            $table->unsignedSmallInteger('unidades_por_caja')->nullable()->after('precio_unitario');
        });

        DB::statement(
            'ALTER TABLE oportunidad_productos ADD CONSTRAINT oportunidad_productos_caja_completa
             CHECK (unidades_por_caja IS NULL OR (unidades_por_caja BETWEEN 1 AND 999 AND cantidad % unidades_por_caja = 0))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE oportunidad_productos DROP CHECK oportunidad_productos_caja_completa');

        Schema::table('oportunidad_productos', function (Blueprint $table) {
            $table->dropColumn('unidades_por_caja');
        });
    }
};
