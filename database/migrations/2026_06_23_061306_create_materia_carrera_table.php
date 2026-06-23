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
        Schema::create('materia_carrera', function (Blueprint $t) {
            $t->id('id_materia_carrera');
            $t->foreignId('id_materia')->constrained('materias', 'id_materia');
            $t->foreignId('id_carrera')->constrained('carreras', 'id_carrera');
            $t->integer('semestre'); // en que semestre de esa carrera
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materia_carrera');
    }
};
