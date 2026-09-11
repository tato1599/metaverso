<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El puente entre una actividad de Moodle y la práctica que representa.
 *
 * Antes, el contexto AGS (a qué línea del libro de calificaciones escribir)
 * viajaba en la sesión que el launch creaba en el acto. Al meter la reserva
 * entre el launch y el juego, la sesión se crea después —desde la API del
 * juego— y perdería ese contexto: la calificación dejaría de volver a Moodle.
 *
 * Aquí se guarda por (alumno, práctica) y se refresca en cada launch, para que
 * cualquier sesión posterior lo encuentre por muchos días que pasen en medio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lti_vinculos_actividad', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_alumno')->constrained('alumnos', 'id_alumno')->cascadeOnDelete();
            $t->foreignId('id_practica')->constrained('practicas', 'id_practica')->cascadeOnDelete();
            $t->foreignId('lti_platform_id')->nullable()->constrained('lti_platforms')->nullOnDelete();
            $t->string('ags_lineitem_url')->nullable();
            $t->string('ags_endpoint')->nullable();
            $t->timestamps();

            // Un alumno tiene UN vínculo vigente por práctica: el último launch manda.
            $t->unique(['id_alumno', 'id_practica']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lti_vinculos_actividad');
    }
};
