<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\SesionPractica;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PanelResultadoController extends Controller
{
    public function __construct(private PanelController $panel) {}

    public function index(Request $request, Grupo $grupo)
    {
        $this->panel->autorizarGrupo($request->user(), $grupo);
        $eventoIds = $grupo->eventos()->pluck('id_evento');
        $sesiones = SesionPractica::whereIn('id_evento', $eventoIds)
            ->with(['alumno.usuario', 'practica'])
            ->orderByDesc('fecha_inicio')
            ->get();

        return Inertia::render('Panel/Resultados', [
            'grupo' => [
                'id_grupo' => $grupo->id_grupo,
                'clave' => $grupo->clave,
            ],
            'sesiones' => $sesiones->map(fn ($s) => [
                'id_sesion' => $s->id_sesion,
                'alumno' => trim(($s->alumno?->usuario?->nombre ?? '').' '.($s->alumno?->usuario?->apellidos ?? '')),
                'practica' => $s->practica?->titulo,
                'estatus' => $s->estatus,
                'calificacion' => $s->calificacion,
                'inicio' => $s->fecha_inicio?->format('Y-m-d H:i'),
            ])->values(),
        ]);
    }

    public function sesion(Request $request, SesionPractica $sesion)
    {
        $sesion->load(['alumno.usuario', 'practica', 'evento']);
        if ($sesion->evento === null) {
            abort(404, 'Esta sesión no pertenece a un grupo (sesión LTI).');
        }
        $grupo = Grupo::findOrFail($sesion->evento->id_grupo);
        $this->panel->autorizarGrupo($request->user(), $grupo);

        return Inertia::render('Panel/Sesion', [
            'sesion' => [
                'id_sesion' => $sesion->id_sesion,
                'alumno' => trim(($sesion->alumno?->usuario?->nombre ?? '').' '.($sesion->alumno?->usuario?->apellidos ?? '')),
                'practica' => $sesion->practica?->titulo,
                'estatus' => $sesion->estatus,
                'calificacion' => $sesion->calificacion,
                'inicio' => $sesion->fecha_inicio?->format('Y-m-d H:i'),
                'fin' => $sesion->fecha_fin?->format('Y-m-d H:i'),
                'datos_resultado' => $sesion->datos_resultado,
                // id_grupo puede ser null en sesiones LTI; el front manda el back-link al dashboard.
                'id_grupo' => $sesion->evento?->id_grupo,
            ],
        ]);
    }
}
