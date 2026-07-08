<?php

namespace App\Http\Controllers\Mi;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Inscripcion;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservaController extends Controller
{
    /**
     * Reservar lugar en un slot — transacción con lock de fila conforme a
     * RESERVA_SECUENCIA.md: el SELECT ... FOR UPDATE del evento serializa el
     * conteo de cupo para que dos alumnos no tomen el último lugar a la vez.
     */
    public function store(Request $request)
    {
        $datos = $request->validate(['id_evento' => 'required|integer']);
        $alumno = $request->user()->alumno;
        abort_unless($alumno, 403);

        DB::transaction(function () use ($alumno, $datos) {
            $evento = EventoAgenda::whereKey($datos['id_evento'])->lockForUpdate()->firstOrFail();

            if ($evento->estatus !== 'programado' || $evento->fecha_hora_inicio->isPast()) {
                throw ValidationException::withMessages(['evento' => 'Este slot ya no acepta reservas.']);
            }

            $inscrito = Inscripcion::where('id_alumno', $alumno->id_alumno)
                ->where('id_grupo', $evento->id_grupo)
                ->where('estatus', 'activa')
                ->exists();
            abort_unless($inscrito, 403, 'No estás inscrito en este grupo.');

            if ($evento->reservasActivas()->where('id_alumno', $alumno->id_alumno)->exists()) {
                throw ValidationException::withMessages(['evento' => 'Ya tienes una reserva en este slot.']);
            }

            if ($evento->reservasActivas()->count() >= $evento->cupo_maximo) {
                throw ValidationException::withMessages(['evento' => 'Slot lleno, elige otro horario.']);
            }

            Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
        });

        return back()->with('success', 'Reserva confirmada.');
    }

    public function destroy(Request $request, Reserva $reserva)
    {
        $alumno = $request->user()->alumno;
        abort_unless($alumno && $reserva->id_alumno === $alumno->id_alumno, 403);

        if ($reserva->estatus !== 'activa' || $reserva->evento->fecha_hora_inicio->isPast()) {
            throw ValidationException::withMessages(['reserva' => 'Esta reserva ya no se puede cancelar.']);
        }

        $reserva->update(['estatus' => 'cancelada']);

        return back()->with('success', 'Reserva cancelada.');
    }
}
