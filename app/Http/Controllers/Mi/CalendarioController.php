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

        $ahora = now();

        return Inertia::render('Mi/Calendario', [
            'semana' => $inicio->toDateString(),
            'eventos' => $eventos->map(function ($e) use ($miasActivas, $ahora) {
                $mia = $miasActivas->get($e->id_evento);
                $activas = (int) $e->reservas_activas;

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
                    'mi_reserva' => $mia ? ['id_reserva' => $mia->id_reserva] : null,
                    // La UI no reimplementa reglas: los booleanos se deciden aquí.
                    'puede_reservar' => ! $mia && $e->estatus === 'programado'
                        && $e->fecha_hora_inicio->isFuture() && $activas < $e->cupo_maximo,
                    'lleno' => ! $mia && $e->estatus === 'programado'
                        && $e->fecha_hora_inicio->isFuture() && $activas >= $e->cupo_maximo,
                    'finalizado' => $e->estatus !== 'cancelado' && $e->fecha_hora_fin->lt($ahora),
                    'puede_cancelar' => (bool) $mia && $e->fecha_hora_inicio->isFuture(),
                    'puede_jugar' => (bool) $mia && $e->estatus !== 'cancelado'
                        && $ahora->between($e->fecha_hora_inicio, $e->fecha_hora_fin),
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
