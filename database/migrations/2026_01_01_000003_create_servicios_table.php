<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "servicios": cada servicio pertenece a un profesional.
 * Define nombre, precio, duración y modalidad de atención.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesional_id')->constrained('profesionales')->cascadeOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 10, 2);
            $table->unsignedSmallInteger('duracion_minutos');
            $table->enum('modalidad', ['presencial', 'remota', 'hibrida'])->default('presencial');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
