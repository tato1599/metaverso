<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // La práctica solo apunta a una escena de Godot (escena_referencia); los
    // parámetros de jugabilidad por tipo quedaron fuera de alcance, así que la
    // columna config ya no se usa.
    public function up(): void
    {
        Schema::table('practicas', function (Blueprint $t) {
            $t->dropColumn('config');
        });
    }

    public function down(): void
    {
        Schema::table('practicas', function (Blueprint $t) {
            $t->jsonb('config')->nullable()->after('escena_referencia');
        });
    }
};
