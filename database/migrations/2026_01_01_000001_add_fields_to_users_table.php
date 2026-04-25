<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos personalizados a la tabla "users" que crea Laravel por defecto.
 * Se mantiene el nombre de la tabla en inglés (convención interna de Laravel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['cliente', 'profesional', 'admin'])->default('cliente')->after('email');
            $table->string('telefono', 20)->nullable()->after('rol');
            $table->string('avatar')->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol', 'telefono', 'avatar']);
        });
    }
};
