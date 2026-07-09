<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // dateTime como fecha_hora_inicio del evento: mismo tipo, misma TZ campus.
        Schema::table('reservas', fn (Blueprint $t) => $t->dateTime('inicio_slot')->nullable());

        // Backfill: toda reserva previa a los sub-slots vivía en la ventana completa,
        // así que su horario es el inicio del evento (misma regla que el hook creating).
        DB::statement(<<<'SQL'
            UPDATE reservas SET inicio_slot = e.fecha_hora_inicio
            FROM eventos_agenda e
            WHERE reservas.id_evento = e.id_evento
        SQL);

        DB::statement('ALTER TABLE reservas ALTER COLUMN inicio_slot SET NOT NULL');

        // Enmienda F8: el índice parcial único (id_evento, id_alumno) sigue evitando
        // dobles reservas, pero ya NO cubre el conteo de cupo (ahora es por slot);
        // la corrección del conteo la sostiene el lockForUpdate del evento. Este
        // índice parcial acelera el conteo por (evento, horario).
        DB::statement("CREATE INDEX reservas_evento_slot_activa ON reservas (id_evento, inicio_slot) WHERE estatus = 'activa'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reservas_evento_slot_activa');
        Schema::table('reservas', fn (Blueprint $t) => $t->dropColumn('inicio_slot'));
    }
};
