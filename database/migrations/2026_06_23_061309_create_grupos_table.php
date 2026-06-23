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
        Schema::create('grupos', function (Blueprint $t) {
            $t->id('id_grupo');
            $t->foreignId('id_materia')->constrained('materias', 'id_materia');
            $t->foreignId('id_maestro')->constrained('maestros', 'id_maestro');
            $t->foreignId('id_ciclo')->constrained('ciclos_escolares', 'id_ciclo');
            $t->string('clave'); // 3A, 5B
            $t->integer('cupo_maximo');
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
