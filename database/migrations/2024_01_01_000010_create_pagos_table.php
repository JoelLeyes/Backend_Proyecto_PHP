<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->morphs('pagable');               // pagable_id + pagable_type
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('moneda', 3)->default('USD');
            $table->string('paypal_order_id')->nullable()->unique();
            $table->string('paypal_capture_id')->nullable()->unique();
            $table->string('estado')->default('pendiente'); // pendiente|completado|fallido
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
