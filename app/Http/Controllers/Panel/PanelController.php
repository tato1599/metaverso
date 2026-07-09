<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PanelController extends Controller
{
    public function gruposVisibles(Usuario $u)
    {
        $q = Grupo::with(['materia', 'ciclo'])->withCount('inscripciones');
        if (! $u->esCoordinadorOAdmin()) {
            $q->where('id_maestro', optional($u->maestro)->id_maestro);
        }

        return $q;
    }

    public function dashboard(Request $request)
    {
        $grupos = $this->gruposVisibles($request->user())->get();

        return Inertia::render('Panel/Dashboard', [
            'grupos' => $grupos->map(fn ($g) => [
                'id_grupo' => $g->id_grupo,
                'clave' => $g->clave,
                'materia' => optional($g->materia)->nombre,
                'ciclo' => optional($g->ciclo)->nombre,
                'alumnos' => $g->inscripciones_count,
            ])->values(),
        ]);
    }

    public function autorizarGrupo(Usuario $u, Grupo $grupo): void
    {
        if ($u->esCoordinadorOAdmin()) {
            return;
        }
        abort_unless($grupo->id_maestro === optional($u->maestro)->id_maestro, 403, 'No puedes ver este grupo.');
    }

    public function show(Request $request, Grupo $grupo)
    {
        $this->autorizarGrupo($request->user(), $grupo);
        $grupo->load(['materia', 'ciclo']);
        $alumnos = $grupo->inscripciones()->with('alumno.usuario')->get();
        $eventos = $grupo->eventos()->with('practica')->get();

        return Inertia::render('Panel/Grupo', [
            'grupo' => [
                'id_grupo' => $grupo->id_grupo,
                'clave' => $grupo->clave,
                'materia' => optional($grupo->materia)->nombre,
                'ciclo' => optional($grupo->ciclo)->nombre,
            ],
            'alumnos' => $alumnos->map(fn ($i) => [
                'id_inscripcion' => $i->id_inscripcion,
                'matricula' => optional($i->alumno)->matricula,
                'nombre' => trim(optional(optional($i->alumno)->usuario)->nombre.' '.optional(optional($i->alumno)->usuario)->apellidos),
            ])->values(),
            'eventos' => $eventos->map(fn ($e) => [
                'id_evento' => $e->id_evento,
                'practica' => optional($e->practica)->titulo,
                'inicio_local' => $e->fecha_hora_inicio->format('Y-m-d\TH:i'),
                'estatus' => $e->estatus,
            ])->values(),
            // El form nativo de "Generar links" postea a un endpoint que aún responde
            // Blade (Task B); router.post de Inertia rompería con esa respuesta.
        ]);
    }
}
