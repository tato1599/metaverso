<?php

namespace App\Http\Controllers\Mi;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Inscripcion;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class CalendarioController extends Controller
{
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

        $eventos = EventoAgenda::with(['practica', 'grupo.materia', 'espacio'])
            ->withCount(['reservas as reservas_activas' => fn ($q) => $q->where('estatus', 'activa')])
            ->whereIn('id_grupo', $gruposActivos)
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
            'eventos' => $eventos->map(function (EventoAgenda $e) use ($miasActivas, $ocupadosPorEvento, $ahora) {
                $mia = $miasActivas->get($e->id_evento);
                $activas = (int) $e->reservas_activas;
                $ocupados = $ocupadosPorEvento->get($e->id_evento, collect());
                $slotsCrudos = $e->slots();

                $slots = collect($slotsCrudos)->map(function (array $s) use ($e, $mia, $ocupados) {
                    $clave = $s['inicio']->format('Y-m-d\TH:i');
                    $enSlot = (int) $ocupados->get($clave, 0);
                    $llenoSlot = $enSlot >= $e->cupo_maximo;

                    return [
                        'inicio_local' => $clave,
                        'fin_local' => $s['fin']->format('Y-m-d\TH:i'),
                        'ocupados' => $enSlot,
                        'lleno' => $llenoSlot,
                        'es_mio' => (bool) $mia && $mia->inicio_slot->format('Y-m-d\TH:i') === $clave,
                        'puede_reservar' => ! $mia && $e->estatus === 'programado'
                            && $s['inicio']->isFuture() && ! $llenoSlot,
                    ];
                })->values();

                // Ventana de juego = slot reservado, capada al fin del evento (F6).
                $finSlotMio = null;
                if ($mia) {
                    $duracion = $e->duracionSlotMinutos();
                    $finSlotMio = $duracion === null
                        ? $e->fecha_hora_fin
                        : $mia->inicio_slot->copy()->addMinutes($duracion)->min($e->fecha_hora_fin);
                }

                return [
                    'id_evento' => $e->id_evento,
                    'practica' => $e->practica->titulo,
                    'materia' => $e->grupo->materia->nombre,
                    'grupo' => $e->grupo->clave,
                    'espacio' => optional($e->espacio)->nombre,
                    'inicio_local' => $e->fecha_hora_inicio->format('Y-m-d\TH:i'),
                    'fin_local' => $e->fecha_hora_fin->format('Y-m-d\TH:i'),
                    'estatus' => $e->estatus,
                    'cupo_maximo' => $e->cupo_maximo,
                    'reservas_activas' => $activas,
                    'multi_slot' => count($slotsCrudos) > 1,
                    'slots' => $slots,
                    'mi_reserva' => $mia ? [
                        'id_reserva' => $mia->id_reserva,
                        'inicio_slot_local' => $mia->inicio_slot->format('Y-m-d\TH:i'),
                    ] : null,
                    // La UI no reimplementa reglas: los booleanos se deciden aquí.
                    'puede_reservar' => $slots->contains(fn (array $s) => $s['puede_reservar']),
                    'lleno' => $e->estatus === 'programado'
                        && collect($slotsCrudos)->contains(fn (array $s) => $s['inicio']->isFuture())
                        && $slots->every(fn (array $s) => $s['lleno']),
                    'finalizado' => $e->estatus !== 'cancelado' && $e->fecha_hora_fin->lt($ahora),
                    'puede_cancelar' => (bool) $mia && $mia->inicio_slot->isFuture(),
                    'puede_jugar' => (bool) $mia && $e->estatus !== 'cancelado'
                        && $ahora->between($mia->inicio_slot, $finSlotMio),
                ];
            })->values(),
            'misReservas' => Reserva::where('id_alumno', $alumno->id_alumno)
                ->with(['evento.practica', 'evento.grupo'])
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn ($r) => [
                    'id_reserva' => $r->id_reserva,
                    'practica' => $r->evento->practica->titulo,
                    'grupo' => $r->evento->grupo->clave,
                    'inicio_local' => $r->evento->fecha_hora_inicio->format('Y-m-d\TH:i'),
                    'estatus' => $r->estatus,
                    'estatus_evento' => $r->evento->estatus,
                ])->values(),
        ]);
    }
}
