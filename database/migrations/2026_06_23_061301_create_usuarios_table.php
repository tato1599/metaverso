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
        Schema::create('usuarios', function (Blueprint $t) {
            $t->id('id_usuario');
            $t->foreignId('id_rol')->constrained('roles', 'id_rol');
            $t->string('correo')->unique();
            $t->string('contrasena_hash')->nullable();
            $t->string('nombre');
            $t->string('apellidos');
            $t->boolean('activo')->default(true);
            $t->timestamp('fecha_creacion')->useCurrent();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
