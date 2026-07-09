<?php

namespace App\Http\Controllers\Mi;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Inscripcion;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservaController extends Controller
{
    /**
     * Reservar lugar en un horario — transacción con lock de fila conforme a
     * RESERVA_SECUENCIA.md: el SELECT ... FOR UPDATE del evento serializa el
     * conteo de cupo POR SLOT para que dos alumnos no tomen el último lugar a la vez.
     *
     * inicio_slot es opcional en eventos de un solo slot (el horario es implícito);
     * en eventos multi-slot su ausencia o un valor fuera de la partición dan 422 (F1).
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'id_evento' => 'required|integer',
            // Solo formato aquí (F3): la pertenencia a la partición se decide
            // contra los slots del servidor, dentro de la transacción.
            'inicio_slot' => 'nullable|date_format:Y-m-d\TH:i',
        ]);
        $alumno = $request->user()->alumno;
        abort_unless($alumno, 403);

        DB::transaction(function () use ($alumno, $datos) {
            $evento = EventoAgenda::whereKey($datos['id_evento'])->lockForUpdate()->firstOrFail();

            $slot = $this->resolverSlot($evento, $datos['inicio_slot'] ?? null);

            // Guarda por slot, no por evento (F2): una ventana ya iniciada sigue
            // aceptando reservas para sus horarios futuros.
            if ($evento->estatus !== 'programado' || $slot['inicio']->isPast()) {
                throw ValidationException::withMessages(['evento' => 'Este horario ya no acepta reservas.']);
            }

            $inscrito = Inscripcion::where('id_alumno', $alumno->id_alumno)
                ->where('id_grupo', $evento->id_grupo)
                ->where('estatus', 'activa')
                ->exists();
            abort_unless($inscrito, 403, 'No estás inscrito en este grupo.');

            // Una sola reserva activa por evento aunque haya slots libres:
            // cambiar de horario = cancelar + re-reservar.
            if ($evento->reservasActivas()->where('id_alumno', $alumno->id_alumno)->exists()) {
                throw ValidationException::withMessages(['evento' => 'Ya tienes una reserva en esta práctica.']);
            }

            if ($evento->reservasActivas()->where('inicio_slot', $slot['inicio'])->count() >= $evento->cupo_maximo) {
                throw ValidationException::withMessages(['evento' => 'Horario lleno, elige otro.']);
            }

            Reserva::create([
                'id_evento' => $evento->id_evento,
                'id_alumno' => $alumno->id_alumno,
                'inicio_slot' => $slot['inicio'],
            ]);
        });

        return back()->with('success', 'Reserva confirmada.');
    }

    public function destroy(Request $request, Reserva $reserva)
    {
        $alumno = $request->user()->alumno;
        abort_unless($alumno && $reserva->id_alumno === $alumno->id_alumno, 403);

        // F2: se puede cancelar mientras el horario reservado no haya iniciado,
        // aunque la ventana del evento ya esté en curso.
        if ($reserva->estatus !== 'activa' || $reserva->inicio_slot->isPast()) {
            throw ValidationException::withMessages(['reserva' => 'Esta reserva ya no se puede cancelar.']);
        }

        $reserva->update(['estatus' => 'cancelada']);

        return back()->with('success', 'Reserva cancelada.');
    }

    /**
     * Match por STRING contra la partición del servidor (F3): siempre regresa el
     * Carbon derivado del evento — nunca se persiste ni se cuenta con el valor
     * parseado del cliente, para que lo almacenado coincida bit a bit con el
     * backfill y con slots de ventanas cuyo inicio trae segundos != 0.
     *
     * @return array{inicio: Carbon, fin: Carbon}
     */
    private function resolverSlot(EventoAgenda $evento, ?string $inicioSlot): array
    {
        $slots = $evento->slots();

        if ($inicioSlot === null) {
            if (count($slots) === 1) {
                return $slots[0];
            }

            throw ValidationException::withMessages(['inicio_slot' => 'Elige un horario.']);
        }

        $slot = collect($slots)->first(
            fn (array $s) => $s['inicio']->format('Y-m-d\TH:i') === $inicioSlot
        );
        if ($slot === null) {
            throw ValidationException::withMessages(['inicio_slot' => 'Ese horario no existe en esta práctica.']);
        }

        return $slot;
    }
}
