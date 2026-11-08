<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de etapas (R5). Solo created_at (el enunciado lo exceptúa de updated_at).
 * etapa_anterior NULL = registro de creación.
 */
return new class extends Migration
{
    public function up(): void
    {
        $etapas = ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida'];

        Schema::create('historial_etapas', function (Blueprint $table) use ($etapas) {
            $table->id();
            $table->foreignId('oportunidad_id')->constrained('oportunidades')
                ->cascadeOnDelete()->restrictOnUpdate();
            $table->foreignId('usuario_id')->constrained('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->enum('etapa_anterior', $etapas)->nullable();
            $table->enum('etapa_nueva', $etapas);
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_etapas');
    }
};
