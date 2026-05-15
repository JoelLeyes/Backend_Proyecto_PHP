<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "ubicaciones": almacena ubicaciones reutilizables por profesional.
 * Cada ubicación pertenece a un profesional y puede usarse en múltiples servicios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesional_id')->constrained('profesionales')->cascadeOnDelete();
            $table->string('nombre'); // Ej: "Consultorio 1", "Oficina", "Centro"
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('pais')->nullable();
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicaciones');
    }
};
