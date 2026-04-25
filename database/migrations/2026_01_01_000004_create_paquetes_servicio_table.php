<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "paquetes_servicio": un profesional puede ofrecer paquetes
 * con múltiples sesiones a precio especial (ej: 4, 6 u 8 encuentros).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paquetes_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedTinyInteger('cantidad_sesiones');
            $table->decimal('precio', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paquetes_servicio');
    }
};
