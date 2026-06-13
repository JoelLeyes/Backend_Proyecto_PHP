<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reservas', 'recordatorio_anticipado_at')) {
            return;
        }

        Schema::table('reservas', function (Blueprint $table) {
            $table->timestamp('recordatorio_anticipado_at')->nullable()->after('recordatorio_enviado_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn('recordatorio_anticipado_at');
        });
    }
};
