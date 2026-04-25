<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "resenas": cada reserva finalizada puede recibir una sola reseña.
 * El cliente evalúa al profesional con calificación del 1 al 5 y comentario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resenas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('evaluador_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('profesional_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('calificacion'); // 1 a 5
            $table->text('comentario')->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->unique('reserva_id'); // una sola reseña por reserva
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resenas');
    }
};
