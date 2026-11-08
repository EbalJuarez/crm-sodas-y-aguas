<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regla propia (spec, sección 6): venta por caja completa.
 * NULL = se vende por unidad. El rango 1–999 evita la división entre cero
 * (en MySQL, x % 0 da NULL y un CHECK lo dejaría pasar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->unsignedSmallInteger('unidades_por_caja')->nullable()->after('precio');
        });

        DB::statement(
            'ALTER TABLE productos ADD CONSTRAINT productos_unidades_por_caja_rango
             CHECK (unidades_por_caja IS NULL OR unidades_por_caja BETWEEN 1 AND 999)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE productos DROP CHECK productos_unidades_por_caja_rango');

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('unidades_por_caja');
        });
    }
};
