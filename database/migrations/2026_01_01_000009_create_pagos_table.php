<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "pagos": registra los pagos del sistema mediante relación polimórfica.
 * Un pago puede corresponder a una reserva individual o a un paquete de cliente.
 * La relación polimórfica (pagable_type + pagable_id) permite esa flexibilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->morphs('pagable'); // crea pagable_type + pagable_id
            $table->foreignId('pagador_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('moneda', 3)->default('USD');
            $table->enum('estado', ['pendiente', 'completado', 'fallido', 'reembolsado'])->default('pendiente');
            $table->enum('pasarela', ['paypal', 'stripe', 'manual'])->default('manual');
            $table->string('id_transaccion')->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
