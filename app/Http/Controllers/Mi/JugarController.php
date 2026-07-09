<?php

namespace App\Http\Controllers\Mi;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Inscripcion;
use App\Models\Reserva;
use App\Models\TokenJuego;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Único punto de lanzamiento del juego desde la agenda (camino con reserva).
 * Punto de extensión del guard global de sesiones concurrentes (CONTEXTO §8).
 */
class JugarController extends Controller
{
    public function __invoke(Request $request, EventoAgenda $evento, MagicLinkService $magicLink)
    {
        $u = $request->user();
        $alumno = $u->alumno;
        abort_unless($alumno, 403);

        $reserva = $evento->reservasActivas()->where('id_alumno', $alumno->id_alumno)->first();
        abort_unless($reserva, 403, 'Necesitas una reserva activa en este slot.');

        // Un exalumno con reserva vieja no debe poder crear sesiones calificables.
        $inscrito = Inscripcion::where('id_alumno', $alumno->id_alumno)
            ->where('id_grupo', $evento->id_grupo)
            ->where('estatus', 'activa')
            ->exists();
        abort_unless($inscrito, 403, 'Ya no estás inscrito en este grupo.');

        if ($evento->estatus === 'cancelado') {
            throw ValidationException::withMessages(['evento' => 'Esta práctica fue cancelada.']);
        }
        // ponytail: sin margen de tolerancia previo al inicio; si se pide, es una clave de config.
        // F6: la ventana de juego es la del slot reservado, no la del evento completo.
        if (! now()->between($reserva->inicio_slot, $this->finDelSlot($evento, $reserva))) {
            throw ValidationException::withMessages(['evento' => 'Tu horario reservado no está en curso en este momento.']);
        }

        // Invalida TODOS los tokens no canjeados del par (usuario, evento), incluidos
        // los emitidos por el maestro vía panel o /api/links: el alumno siempre queda
        // con exactamente un enlace vigente. El lock del evento serializa dos POST
        // concurrentes (mismo orden de locks que ReservaController::store).
        $res = DB::transaction(function () use ($u, $evento, $magicLink) {
            EventoAgenda::whereKey($evento->id_evento)->lockForUpdate()->firstOrFail();
            TokenJuego::where('id_usuario', $u->id_usuario)
                ->where('id_evento', $evento->id_evento)
                ->where('usado', false)
                ->update(['usado' => true]);

            return $magicLink->generar($u->id_usuario, $evento->id_evento);
        });

        // /jugar/{token} es Blade y dispara un deeplink de esquema custom: exige
        // navegación top-level, por eso Inertia::location y no un redirect normal.
        return Inertia::location($res['url']);
    }

    /**
     * Fin de la ventana de juego del slot reservado: inicio_slot + duración,
     * capado al fin del evento (reservas heredadas o ventanas no divisibles).
     */
    private function finDelSlot(EventoAgenda $evento, Reserva $reserva): Carbon
    {
        $duracion = $evento->duracionSlotMinutos();

        return $duracion === null
            ? $evento->fecha_hora_fin
            : $reserva->inicio_slot->copy()->addMinutes($duracion)->min($evento->fecha_hora_fin);
    }
}
