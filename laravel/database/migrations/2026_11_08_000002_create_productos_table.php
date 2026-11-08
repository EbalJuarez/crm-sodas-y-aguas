<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo (R6). El precio es DECIMAL(10,2), nunca FLOAT.
 * Más de 2 decimales lo rechaza la validación de Laravel: MySQL lo redondearía sin avisar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->decimal('precio', 10, 2);
            $table->boolean('activo')->default(true);
            $table->datetimes();
        });

        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_precio_positivo CHECK (precio > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
