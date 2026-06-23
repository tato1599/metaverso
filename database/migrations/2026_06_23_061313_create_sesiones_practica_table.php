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
        Schema::create('sesiones_practica', function (Blueprint $t) {
            $t->id('id_sesion');
            $t->foreignId('id_evento')->constrained('eventos_agenda', 'id_evento');
            $t->foreignId('id_alumno')->constrained('alumnos', 'id_alumno');
            $t->foreignId('id_practica')->constrained('practicas', 'id_practica');
            $t->dateTime('fecha_inicio');
            $t->dateTime('fecha_fin')->nullable();
            $t->string('estatus')->default('en_progreso'); // en_progreso, completada, abandonada
            $t->float('calificacion')->nullable();
            $t->jsonb('datos_resultado')->nullable(); // telemetria cruda del juego
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesiones_practica');
    }
};
