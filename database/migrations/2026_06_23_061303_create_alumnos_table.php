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
        Schema::create('alumnos', function (Blueprint $t) {
            $t->id('id_alumno');
            $t->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $t->foreignId('id_carrera')->constrained('carreras', 'id_carrera');
            $t->string('matricula')->unique();
            $t->integer('semestre_actual');
            $t->string('generacion');
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
