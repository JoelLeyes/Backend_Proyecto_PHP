<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "excepciones_disponibilidad": días puntuales en los que el profesional
 * no está disponible (feriados, licencias) o agrega disponibilidad extra.
 * disponible=false significa día bloqueado; true significa día habilitado extra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excepciones_disponibilidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesional_id')->constrained('profesionales')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('motivo')->nullable();
            $table->boolean('disponible')->default(false);
            $table->timestamps();

            $table->unique(['profesional_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excepciones_disponibilidad');
    }
};
