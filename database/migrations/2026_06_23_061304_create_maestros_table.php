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
        Schema::create('maestros', function (Blueprint $t) {
            $t->id('id_maestro');
            $t->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
            $t->string('numero_empleado')->unique();
            $t->string('grado_academico')->nullable();
            $t->string('especialidad')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maestros');
    }
};
