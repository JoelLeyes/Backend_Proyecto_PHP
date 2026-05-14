<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL CHECK constraint — add 'pendiente_pago' to allowed states
        DB::statement("ALTER TABLE paquetes_cliente DROP CONSTRAINT IF EXISTS paquetes_cliente_estado_check");
        DB::statement("ALTER TABLE paquetes_cliente ADD CONSTRAINT paquetes_cliente_estado_check CHECK (estado IN ('pendiente_pago','activo','consumido','vencido'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE paquetes_cliente DROP CONSTRAINT IF EXISTS paquetes_cliente_estado_check");
        DB::statement("ALTER TABLE paquetes_cliente ADD CONSTRAINT paquetes_cliente_estado_check CHECK (estado IN ('activo','consumido','vencido'))");
    }
};
