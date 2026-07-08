<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Practica;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AgendaController extends Controller
{
    public function __construct(private PanelController $panel) {}

    public function index(Request $request)
    {
        try {
            $inicio = $request->query('semana') ? Carbon::parse($request->query('semana')) : now();
        } catch (\Throwable) {
            $inicio = now();
        }
        $inicio = $inicio->startOfWeek();
        $fin = $inicio->copy()->endOfWeek();
        $u = $request->user();

        $eventos = EventoAgenda::with(['practica', 'grupo.materia', 'espacio'])
            ->withCount(['reservas as reservas_activas' => fn ($q) => $q->where('estatus', 'activa')])
            ->whereBetween('fecha_hora_inicio', [$inicio, $fin])
            ->when(! $u->esCoordinadorOAdmin(), fn ($q) => $q->whereHas(
                'grupo', fn ($g) => $g->where('id_maestro', optional($u->maestro)->id_maestro)
            ))
            ->orderBy('fecha_hora_inicio')
            ->get();

        $grupos = $this->panel->gruposVisibles($u)->with('materia.practicas')->get();

        return Inertia::render('Panel/Agenda', [
            'semana' => $inicio->toDateString(),
            'eventos' => $eventos->map(fn ($e) => $this->eventoProps($e)),
            'grupos' => $grupos->map(fn ($g) => [
                'id_grupo' => $g->id_grupo,
                'clave' => $g->clave,
                'materia' => $g->materia->nombre,
                'practicas' => $g->materia->practicas->map(fn ($p) => [
                    'id_practica' => $p->id_practica,
                    'titulo' => $p->titulo,
                ])->values(),
            ])->values(),
            'espacios' => Espacio::all()->map(fn ($e) => [
                'id_espacio' => $e->id_espacio,
                'nombre' => $e->nombre,
                'capacidad' => $e->capacidad,
            ])->values(),
            'cupoDefault' => config('metaverso.cupo_default_evento'),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'id_grupo' => 'required|integer|exists:grupos,id_grupo',
            'id_practica' => 'required|integer|exists:practicas,id_practica',
            'id_espacio' => 'nullable|integer|exists:espacios,id_espacio',
            'fecha_hora_inicio' => 'required|date',
            'fecha_hora_fin' => 'required|date|after:fecha_hora_inicio',
            'cupo_maximo' => 'required|integer|min:1|max:32767',
        ]);

        // 403 de grupo ajeno siempre antes que el 422 de dominio.
        $grupo = Grupo::findOrFail($datos['id_grupo']);
        $this->panel->autorizarGrupo($request->user(), $grupo);

        if (Practica::findOrFail($datos['id_practica'])->id_materia !== $grupo->id_materia) {
            throw ValidationException::withMessages([
                'id_practica' => 'La práctica no pertenece a la materia del grupo.',
            ]);
        }

        EventoAgenda::create([...$datos, 'estatus' => 'programado']);

        return redirect()->route('panel.agenda')->with('success', 'Práctica agendada.');
    }

    public function show(Request $request, EventoAgenda $evento)
    {
        $this->autorizarEvento($request, $evento);
        $evento->load(['practica', 'grupo.materia', 'espacio', 'reservasActivas.alumno.usuario']);

        return Inertia::render('Panel/EventoDetalle', [
            'evento' => $this->eventoProps($evento, $evento->reservasActivas->count()),
            'reservas' => $evento->reservasActivas->map(fn ($r) => [
                'id_reserva' => $r->id_reserva,
                'nombre' => trim($r->alumno->usuario->nombre.' '.$r->alumno->usuario->apellidos),
                'matricula' => $r->alumno->matricula,
                'fecha' => $r->created_at->format('Y-m-d H:i'),
            ])->values(),
            'espacios' => Espacio::all()->map(fn ($e) => [
                'id_espacio' => $e->id_espacio,
                'nombre' => $e->nombre,
                'capacidad' => $e->capacidad,
            ])->values(),
        ]);
    }

    public function update(Request $request, EventoAgenda $evento)
    {
        $this->autorizarEvento($request, $evento);
        abort_if($evento->estatus === 'cancelado', 409, 'El evento está cancelado.');

        // Whitelist estricta: reprogramar solo toca fechas, espacio y cupo (spec §4).
        $datos = $request->validate([
            'fecha_hora_inicio' => 'required|date',
            'fecha_hora_fin' => 'required|date|after:fecha_hora_inicio',
            'id_espacio' => 'nullable|integer|exists:espacios,id_espacio',
            'cupo_maximo' => 'required|integer|min:1|max:32767',
        ]);

        // Misma receta que reservar: lock del evento antes de contar (evita carrera con reservas nuevas).
        DB::transaction(function () use ($evento, $datos) {
            $bloqueado = EventoAgenda::whereKey($evento->id_evento)->lockForUpdate()->firstOrFail();
            // Re-verificar sobre la fila bloqueada: un destroy() concurrente pudo cancelarlo (TOCTOU).
            abort_if($bloqueado->estatus === 'cancelado', 409, 'El evento está cancelado.');
            $activas = $bloqueado->reservasActivas()->count();
            if ($datos['cupo_maximo'] < $activas) {
                throw ValidationException::withMessages([
                    'cupo_maximo' => "Hay {$activas} reservas activas; el cupo no puede ser menor.",
                ]);
            }
            $bloqueado->update($datos);
        });

        return redirect()->route('panel.eventos.show', $evento)->with('success', 'Evento actualizado.');
    }

    public function destroy(Request $request, EventoAgenda $evento)
    {
        $this->autorizarEvento($request, $evento);
        abort_if($evento->estatus === 'cancelado', 409, 'El evento ya está cancelado.');

        $evento->update(['estatus' => 'cancelado']);

        return redirect()->route('panel.agenda')->with('success', 'Evento cancelado.');
    }

    private function autorizarEvento(Request $request, EventoAgenda $evento): void
    {
        $this->panel->autorizarGrupo($request->user(), $evento->grupo);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventoProps(EventoAgenda $e, ?int $reservasActivas = null): array
    {
        return [
            'id_evento' => $e->id_evento,
            'practica' => $e->practica->titulo,
            'grupo' => $e->grupo->clave,
            'materia' => $e->grupo->materia->nombre,
            'espacio' => optional($e->espacio)->nombre,
            'id_espacio' => $e->id_espacio,
            'inicio' => $e->fecha_hora_inicio->toIso8601String(),
            'fin' => $e->fecha_hora_fin->toIso8601String(),
            'inicio_local' => $e->fecha_hora_inicio->format('Y-m-d\TH:i'),
            'fin_local' => $e->fecha_hora_fin->format('Y-m-d\TH:i'),
            'estatus' => $e->estatus,
            'cupo_maximo' => $e->cupo_maximo,
            'reservas_activas' => $reservasActivas ?? (int) ($e->reservas_activas ?? 0),
        ];
    }
}
