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
        Schema::create('practicas', function (Blueprint $t) {
            $t->id('id_practica');
            $t->foreignId('id_materia')->constrained('materias', 'id_materia');
            $t->string('titulo');
            $t->text('descripcion')->nullable();
            $t->text('objetivos')->nullable();
            $t->integer('duracion_estimada')->nullable(); // minutos
            $t->integer('orden')->default(1);
            $t->string('escena_referencia')->nullable(); // id de nivel/escena en Unreal
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practicas');
    }
};
