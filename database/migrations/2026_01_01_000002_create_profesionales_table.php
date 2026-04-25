<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "profesionales": extiende el perfil de un usuario con rol profesional.
 * Almacena datos de negocio, ubicación y política de cancelación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesionales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre_negocio')->nullable();
            $table->text('bio')->nullable();
            $table->enum('modalidad', ['presencial', 'remota', 'hibrida'])->default('presencial');
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('pais', 3)->nullable();
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->unsignedSmallInteger('horas_cancelacion')->default(24);
            $table->decimal('promedio_calificacion', 3, 2)->default(0);
            $table->unsignedInteger('total_calificaciones')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesionales');
    }
};
