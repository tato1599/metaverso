<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_agenda', function (Blueprint $t) {
            $t->unsignedSmallInteger('cupo_maximo')->default(5);
        });
        // Eventos preexistentes: heredan capacidad del espacio si es menor (CONTEXTO §5),
        // nunca por debajo de 1 (capacidad es nullable y sin check).
        DB::statement(<<<'SQL'
            UPDATE eventos_agenda SET cupo_maximo = GREATEST(1, LEAST(5, e.capacidad))
            FROM espacios e
            WHERE eventos_agenda.id_espacio = e.id_espacio AND e.capacidad IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('eventos_agenda', fn (Blueprint $t) => $t->dropColumn('cupo_maximo'));
    }
};
