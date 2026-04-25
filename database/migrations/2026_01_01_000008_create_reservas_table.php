<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "reservas": representa un turno entre un cliente y un profesional.
 * El ciclo de vida del estado: pendiente → confirmada → pagada → en_curso
 *                               → finalizada | cancelada | no_asistida
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('profesional_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('paquete_cliente_id')->nullable()->constrained('paquetes_cliente')->nullOnDelete();
            $table->dateTime('fecha_hora');
            $table->unsignedSmallInteger('duracion_minutos');
            $table->enum('estado', [
                'pendiente',
                'confirmada',
                'pagada',
                'en_curso',
                'finalizada',
                'cancelada',
                'no_asistida',
            ])->default('pendiente');
            $table->enum('modalidad', ['presencial', 'remota', 'hibrida'])->default('presencial');
            $table->text('notas')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_cancelacion')->nullable();
            $table->timestamps();

            $table->index(['profesional_id', 'fecha_hora']);
            $table->index(['cliente_id', 'fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
