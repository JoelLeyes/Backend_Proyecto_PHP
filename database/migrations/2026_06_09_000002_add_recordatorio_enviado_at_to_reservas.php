<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega recordatorio_enviado_at a reservas.
 * Cuando el comando de recordatorios procesa una reserva, guarda el timestamp
 * para no volver a enviar el aviso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->timestamp('recordatorio_enviado_at')->nullable()->after('motivo_cancelacion');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('recordatorio_enviado_at');
        });
    }
};
