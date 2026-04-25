<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "paquetes_cliente": registra los paquetes adquiridos por un cliente.
 * Lleva el conteo de sesiones usadas y el estado del paquete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paquetes_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('paquete_servicio_id')->constrained('paquetes_servicio')->cascadeOnDelete();
            $table->unsignedTinyInteger('sesiones_total');
            $table->unsignedTinyInteger('sesiones_usadas')->default(0);
            $table->timestamp('fecha_compra')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->enum('estado', ['activo', 'consumido', 'vencido'])->default('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paquetes_cliente');
    }
};
