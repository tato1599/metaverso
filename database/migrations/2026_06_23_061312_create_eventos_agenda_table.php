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
        Schema::create('eventos_agenda', function (Blueprint $t) {
            $t->id('id_evento');
            $t->foreignId('id_practica')->constrained('practicas', 'id_practica');
            $t->foreignId('id_grupo')->constrained('grupos', 'id_grupo');
            $t->foreignId('id_espacio')->nullable()->constrained('espacios', 'id_espacio');
            $t->dateTime('fecha_hora_inicio');
            $t->dateTime('fecha_hora_fin');
            $t->string('estatus')->default('programado'); // programado, en_curso, finalizado, cancelado
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_agenda');
    }
};
