<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $t) {
            $t->id('id_reserva');
            $t->foreignId('id_evento')->constrained('eventos_agenda', 'id_evento');
            $t->foreignId('id_alumno')->constrained('alumnos', 'id_alumno');
            $t->string('estatus')->default('activa'); // activa | cancelada (extensible: lista_espera)
            $t->timestamps(); // created_at = fecha de reserva
            $t->index('id_alumno');
        });
        // Respaldo a nivel BD contra dobles reservas (una activa por alumno y evento).
        // F6: ya no cubre el conteo de cupo — el cupo se cuenta por (id_evento, inicio_slot)
        // y lo serializa el lockForUpdate del evento en la transacción de reservar.
        DB::statement("CREATE UNIQUE INDEX reservas_evento_alumno_activa ON reservas (id_evento, id_alumno) WHERE estatus = 'activa'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
