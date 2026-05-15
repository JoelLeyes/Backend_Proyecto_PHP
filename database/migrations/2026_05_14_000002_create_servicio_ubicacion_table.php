<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "servicio_ubicacion": relación many-to-many entre servicios y ubicaciones.
 * Permite que cada servicio tenga una o múltiples ubicaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_ubicacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->foreignId('ubicacion_id')->constrained('ubicaciones')->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['servicio_id', 'ubicacion_id']); // Un servicio no puede tener la misma ubicación 2 veces
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_ubicacion');
    }
};
