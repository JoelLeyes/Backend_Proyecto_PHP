<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "sesiones_video": almacena la sala y los tokens de videollamada
 * generados para una reserva de modalidad remota (usando LiveKit o WebRTC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones_video', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->string('nombre_sala')->unique();
            $table->text('token_cliente')->nullable();
            $table->text('token_profesional')->nullable();
            $table->timestamp('iniciada_en')->nullable();
            $table->timestamp('finalizada_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_video');
    }
};
