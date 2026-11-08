<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende la tabla users de Laravel sin editar su migración original (L1.2).
 * R1: rol y activo deciden la puerta. R2: cada vendedor pertenece a un supervisor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['administrador', 'supervisor', 'vendedor'])
                ->default('vendedor')
                ->after('password');
            // RESTRICT: MySQL no permite acciones referenciales (CASCADE / SET NULL)
            // en una columna que se usa en un CHECK.
            $table->foreignId('supervisor_id')
                ->nullable()
                ->after('rol')
                ->constrained('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->boolean('activo')->default(true)->after('supervisor_id');
        });

        // Obligatorio para vendedores y vacío para los demás.
        // «Debe ser un supervisor activo» cruza filas: lo valida Laravel al asignar.
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_supervisor_segun_rol
             CHECK ((rol = 'vendedor') = (supervisor_id IS NOT NULL))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CHECK users_supervisor_segun_rol');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropColumn(['rol', 'activo']);
        });
    }
};
