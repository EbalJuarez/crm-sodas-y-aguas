<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Oportunidades (R5). La base hace cumplir la relación entre etapa, motivo de pérdida y fecha
 * de cierre. Las transiciones (D1), «ganar exige un producto» y «cerrada no cambia» cruzan filas
 * o dependen del estado anterior: las hace la API dentro de una transacción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            // RESTRICT: un cliente con oportunidades no se elimina (L4.11).
            $table->foreignId('cliente_id')->constrained('clientes')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('vendedor_id')->constrained('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->string('titulo', 150);
            $table->enum('etapa', ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida'])
                ->default('prospecto');
            $table->string('motivo_perdida', 255)->nullable();
            $table->dateTime('fecha_cierre')->nullable();
            $table->datetimes();

            $table->index(['vendedor_id', 'etapa']);
        });

        DB::statement(
            "ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_motivo_sii_perdida
             CHECK ((etapa = 'perdida') = (motivo_perdida IS NOT NULL))"
        );
        DB::statement(
            "ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_motivo_no_vacio
             CHECK (motivo_perdida IS NULL OR CHAR_LENGTH(TRIM(motivo_perdida)) > 0)"
        );
        DB::statement(
            "ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_cierre_sii_cerrada
             CHECK ((etapa IN ('ganada', 'perdida')) = (fecha_cierre IS NOT NULL))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('oportunidades');
    }
};
