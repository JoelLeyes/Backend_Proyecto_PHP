<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "reglas_disponibilidad": define los horarios laborales del profesional
 * por día de la semana, con buffers opcionales entre turnos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_disponibilidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesional_id')->constrained('profesionales')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 0=domingo ... 6=sábado
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->unsignedSmallInteger('buffer_antes_minutos')->default(0);
            $table->unsignedSmallInteger('buffer_despues_minutos')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['profesional_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_disponibilidad');
    }
};
