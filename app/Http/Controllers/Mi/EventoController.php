<?php

namespace App\Http\Controllers\Mi;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Inscripcion;
use App\Models\Reserva;
use App\Services\EstadoEventoAlumno;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * La vista de una práctica agendada para el alumno.
 *
 * Es UNA sola pantalla que cambia según su estado con esa práctica: si no ha
 * reservado muestra los horarios; si ya reservó muestra su horario; y cuando su
 * ventana está en curso, el botón de jugar. Sustituye al modal que vivía dentro
 * de un chip del calendario, y es la pantalla a la que también aterriza quien
 * entra desde Moodle.
 */
class EventoController extends Controller
{
    public function __construct(private EstadoEventoAlumno $estado) {}

    public function show(Request $request, EventoAgenda $evento)
    {
        $alumno = $request->user()->alumno;
        abort_unless($alumno, 403);

        // Misma regla que reservar: sin inscripción activa no hay nada que ver aquí.
        $inscrito = Inscripcion::where('id_alumno', $alumno->id_alumno)
            ->where('id_grupo', $evento->id_grupo)
            ->where('estatus', 'activa')
            ->exists();
        abort_unless($inscrito, 403, 'No estás inscrito en este grupo.');

        $evento->load(['practica', 'grupo.materia', 'espacio']);
        $ahora = now();

        $mia = Reserva::where('id_alumno', $alumno->id_alumno)
            ->where('id_evento', $evento->id_evento)
            ->where('estatus', 'activa')
            ->first();

        $otros = $this->otrosHorarios($evento, $alumno->id_alumno, $ahora);

        return Inertia::render('Mi/Evento', [
            'evento' => $this->estado->para($evento, $mia, $this->estado->ocupadosPorSlot($evento), $ahora),
            'otros' => $otros,
            // Una práctica se cursa una vez: si el alumno ya la tiene reservada en
            // otra fecha, esta pantalla no puede ofrecerle reservar como si nada.
            'reservaEnOtraFecha' => $mia ? null : $this->reservaEnOtraFecha($otros),
        ]);
    }

    /**
     * La fecha hermana que el alumno ya tiene reservada, si la hay.
     *
     * @param  array<int,array<string,mixed>>  $otros
     * @return array{id_evento:int, id_reserva:int, inicio_local:string, inicio_slot_local:string}|null
     */
    private function reservaEnOtraFecha(array $otros): ?array
    {
        foreach ($otros as $o) {
            if ($o['mi_reserva'] !== null) {
                return [
                    'id_evento' => $o['id_evento'],
                    'id_reserva' => $o['mi_reserva']['id_reserva'],
                    'inicio_local' => $o['inicio_local'],
                    'inicio_slot_local' => $o['mi_reserva']['inicio_slot_local'],
                ];
            }
        }

        return null;
    }

    /**
     * Otras fechas de la MISMA práctica para el MISMO grupo que aún no terminan.
     * Es lo que hace útil la pantalla cuando el maestro agendó la práctica varias
     * veces: el alumno ve dónde más cabe sin volver al calendario.
     *
     * @return array<int,array<string,mixed>>
     */
    private function otrosHorarios(EventoAgenda $evento, int $idAlumno, $ahora): array
    {
        $hermanos = EventoAgenda::with(['practica', 'grupo.materia', 'espacio'])
            ->withCount(['reservas as reservas_activas' => fn ($q) => $q->where('estatus', 'activa')])
            ->where('id_practica', $evento->id_practica)
            ->where('id_grupo', $evento->id_grupo)
            ->whereKeyNot($evento->id_evento)
            ->where('fecha_hora_fin', '>=', $ahora)
            ->orderBy('fecha_hora_inicio')
            ->get();

        if ($hermanos->isEmpty()) {
            return [];
        }

        $mias = Reserva::where('id_alumno', $idAlumno)
            ->where('estatus', 'activa')
            ->whereIn('id_evento', $hermanos->pluck('id_evento'))
            ->get()
            ->keyBy('id_evento');

        return $hermanos->map(fn (EventoAgenda $e) => $this->estado->para(
            $e,
            $mias->get($e->id_evento),
            $this->estado->ocupadosPorSlot($e),
            $ahora,
        ))->values()->all();
    }
}
