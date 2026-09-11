<?php

namespace App\Http\Controllers\Mi;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Inscripcion;
use App\Models\Reserva;
use App\Services\EstadoEventoAlumno;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class CalendarioController extends Controller
{
    public function __construct(private EstadoEventoAlumno $estado) {}

    public function index(Request $request)
    {
        try {
            $inicio = $request->query('semana') ? Carbon::parse($request->query('semana')) : now();
        } catch (\Throwable) {
            $inicio = now();
        }
        $inicio = $inicio->startOfWeek();
        $fin = $inicio->copy()->endOfWeek();

        $alumno = $request->user()->alumno;
        abort_unless($alumno, 403);

        $gruposActivos = Inscripcion::where('id_alumno', $alumno->id_alumno)
            ->where('estatus', 'activa')
            ->pluck('id_grupo');

        // La misma restricción de visibilidad sirve para la semana y para los
        // límites de navegación: así el calendario nunca deja fuera un evento real.
        $visibles = EventoAgenda::whereIn('id_grupo', $gruposActivos);

        $eventos = (clone $visibles)
            ->with(['practica', 'grupo.materia', 'espacio'])
            ->withCount(['reservas as reservas_activas' => fn ($q) => $q->where('estatus', 'activa')])
            ->whereBetween('fecha_hora_inicio', [$inicio, $fin])
            ->orderBy('fecha_hora_inicio')
            ->get();

        $miasActivas = Reserva::where('id_alumno', $alumno->id_alumno)
            ->where('estatus', 'activa')
            ->whereIn('id_evento', $eventos->pluck('id_evento'))
            ->get()
            ->keyBy('id_evento');

        // Ocupación por (evento, horario) en una sola consulta agregada.
        $ocupadosPorEvento = Reserva::whereIn('id_evento', $eventos->pluck('id_evento'))
            ->where('estatus', 'activa')
            ->groupBy('id_evento', 'inicio_slot')
            ->selectRaw('id_evento, inicio_slot, count(*) as total')
            ->get()
            ->groupBy('id_evento')
            ->map(fn ($porSlot) => $porSlot
                ->keyBy(fn ($r) => $r->inicio_slot->format('Y-m-d\TH:i'))
                ->map(fn ($r) => (int) $r->total));

        $ahora = now();

        return Inertia::render('Mi/Calendario', [
            'semana' => $inicio->toDateString(),
            'limites' => EventoAgenda::limitesSemana($visibles),
            // Las reglas viven en EstadoEventoAlumno, no aquí: esta pantalla y la
            // vista de una práctica tienen que decir exactamente lo mismo.
            'eventos' => $eventos->map(fn (EventoAgenda $e) => $this->estado->para(
                $e,
                $miasActivas->get($e->id_evento),
                $ocupadosPorEvento->get($e->id_evento, collect()),
                $ahora,
            ))->values(),
            // Un historial de reservas —canceladas incluidas— es contabilidad, no
            // un panel. Lo que el alumno necesita saber es: qué tengo agendado,
            // qué me falta reservar, y en qué grupos estoy. Van como closures:
            // no dependen de la semana, así que un cambio de semana no las recalcula.
            'proximas' => fn () => $this->proximasReservas($alumno->id_alumno, $ahora),
            'pendientes' => fn () => $this->practicasPendientes($alumno->id_alumno, $gruposActivos, $ahora),
            'grupos' => fn () => $this->gruposInscritos($alumno->id_alumno),
        ]);
    }

    /**
     * Lo que el alumno tiene apartado y todavía no ocurre.
     *
     * @return array<int,array<string,mixed>>
     */
    private function proximasReservas(int $idAlumno, Carbon $ahora): array
    {
        return Reserva::where('id_alumno', $idAlumno)
            ->where('estatus', 'activa')
            ->whereHas('evento', fn ($q) => $q->where('fecha_hora_fin', '>=', $ahora))
            ->with(['evento.practica', 'evento.grupo.materia', 'evento.espacio'])
            ->get()
            ->sortBy('inicio_slot')
            ->map(fn (Reserva $r) => [
                'id_reserva' => $r->id_reserva,
                'id_evento' => $r->id_evento,
                'practica' => $r->evento->practica->titulo,
                'grupo' => $r->evento->grupo->clave,
                'materia' => $r->evento->grupo->materia->nombre,
                'espacio' => optional($r->evento->espacio)->nombre,
                'inicio_slot_local' => $r->inicio_slot->format('Y-m-d\TH:i'),
                'cancelado' => $r->evento->estatus === 'cancelado',
            ])->values()->all();
    }

    /**
     * Prácticas de sus grupos que aún puede reservar y no ha reservado.
     *
     * Se agrupa POR PRÁCTICA, no por evento: cuando el maestro agenda la misma
     * práctica varias veces esas fechas son alternativas, y el alumno solo puede
     * tomar una. Listarlas todas diría "tienes 3 pendientes" cuando es una.
     *
     * @param  Collection<int,int>  $gruposActivos
     * @return array<int,array<string,mixed>>
     */
    private function practicasPendientes(int $idAlumno, $gruposActivos, Carbon $ahora): array
    {
        $yaReservadas = Reserva::where('id_alumno', $idAlumno)
            ->where('estatus', 'activa')
            ->whereHas('evento', fn ($q) => $q->where('fecha_hora_fin', '>=', $ahora))
            ->with('evento')
            ->get()
            ->pluck('evento.id_practica')
            ->unique();

        return EventoAgenda::whereIn('id_grupo', $gruposActivos)
            ->where('estatus', 'programado')
            ->where('fecha_hora_inicio', '>=', $ahora)
            ->whereNotIn('id_practica', $yaReservadas)
            ->with(['practica', 'grupo.materia'])
            ->orderBy('fecha_hora_inicio')
            ->get()
            ->groupBy('id_practica')
            ->map(function ($eventos) {
                $primero = $eventos->first();

                return [
                    'id_practica' => $primero->id_practica,
                    'id_evento' => $primero->id_evento,
                    'practica' => $primero->practica->titulo,
                    'grupo' => $primero->grupo->clave,
                    'materia' => $primero->grupo->materia->nombre,
                    'proxima_local' => $primero->fecha_hora_inicio->format('Y-m-d\TH:i'),
                    'fechas' => $eventos->count(),
                ];
            })
            ->sortBy('proxima_local')
            ->values()
            ->all();
    }

    /**
     * Los grupos en los que está inscrito ahora mismo.
     *
     * @return array<int,array<string,mixed>>
     */
    private function gruposInscritos(int $idAlumno): array
    {
        return Inscripcion::where('id_alumno', $idAlumno)
            ->where('estatus', 'activa')
            ->with(['grupo.materia', 'grupo.ciclo', 'grupo.maestro.usuario'])
            ->get()
            ->map(fn (Inscripcion $i) => [
                'id_grupo' => $i->id_grupo,
                'clave' => $i->grupo->clave,
                'materia' => $i->grupo->materia->nombre,
                'ciclo' => optional($i->grupo->ciclo)->nombre,
                'maestro' => trim(
                    optional(optional($i->grupo->maestro)->usuario)->nombre.' '.
                    optional(optional($i->grupo->maestro)->usuario)->apellidos
                ) ?: null,
            ])->values()->all();
    }
}
