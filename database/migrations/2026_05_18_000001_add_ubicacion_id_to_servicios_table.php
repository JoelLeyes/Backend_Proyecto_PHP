<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->foreignId('ubicacion_id')
                ->nullable()
                ->after('modalidad')
                ->constrained('ubicaciones')
                ->nullOnDelete();
        });

        $servicios = DB::table('servicios')->select('id')->get();

        foreach ($servicios as $servicio) {
            $ubicacionId = DB::table('servicio_ubicacion')
                ->where('servicio_id', $servicio->id)
                ->orderBy('id')
                ->value('ubicacion_id');

            if ($ubicacionId) {
                DB::table('servicios')
                    ->where('id', $servicio->id)
                    ->update(['ubicacion_id' => $ubicacionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ubicacion_id');
        });
    }
};