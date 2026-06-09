<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla "notificaciones_app": persiste el historial de la campana de cada usuario.
 * Se limpia automáticamente cada día (registros > 90 días).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_app', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 20)->default('info');   // success | error | warning | info
            $table->string('icono', 10)->default('🔔');
            $table->string('titulo');
            $table->text('mensaje');
            $table->boolean('leida')->default(false);
            $table->timestamps();

            $table->index(['usuario_id', 'leida']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_app');
    }
};
