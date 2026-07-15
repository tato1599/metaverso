<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practicas', function (Blueprint $t) {
            // Parámetros del mini-juego. Nullable: las prácticas previas resuelven
            // defaults desde el registro (config/juegos.php).
            $t->jsonb('config')->nullable()->after('escena_referencia');
        });
    }

    public function down(): void
    {
        Schema::table('practicas', function (Blueprint $t) {
            $t->dropColumn('config');
        });
    }
};
