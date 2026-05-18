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
            // Nota: esta migración corre antes que create_users_table por el timestamp.
            // Por eso evitamos crear FK a users aquí (se agregará en una migración posterior si se requiere).
            $table->foreignId('cliente_id');
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
