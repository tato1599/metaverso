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
        Schema::create('inscripciones', function (Blueprint $t) {
            $t->id('id_inscripcion');
            $t->foreignId('id_alumno')->constrained('alumnos', 'id_alumno');
            $t->foreignId('id_grupo')->constrained('grupos', 'id_grupo');
            $t->date('fecha_inscripcion');
            $t->string('estatus')->default('activa');
            $t->timestamps();
            $t->unique(['id_alumno', 'id_grupo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
