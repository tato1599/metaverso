<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ciclos_escolares', function (Blueprint $t) {
            $t->id('id_ciclo');
            $t->string('nombre'); // 2026-1, 2026-2
            $t->date('fecha_inicio');
            $t->date('fecha_fin');
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ciclos_escolares');
    }
};
