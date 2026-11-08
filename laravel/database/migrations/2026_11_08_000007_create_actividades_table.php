<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actividades (R2, R8). vendedor_id es quién la registró; la visibilidad sale de la cartera
 * actual del cliente (D2), no de esta columna. vence_en se guarda en UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')
                ->cascadeOnDelete()->restrictOnUpdate();
            $table->foreignId('vendedor_id')->constrained('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->enum('tipo', ['llamada', 'reunion', 'tarea']);
            $table->string('descripcion', 500);
            $table->dateTime('vence_en')->nullable();
            $table->boolean('completada')->default(false);
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
