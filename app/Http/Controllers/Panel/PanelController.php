<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Usuario;
use Illuminate\Http\Request;

class PanelController extends Controller {
    public function gruposVisibles(Usuario $u) {
        $q = Grupo::with(['materia', 'ciclo'])->withCount('inscripciones');
        if (! $u->esCoordinadorOAdmin()) {
            $q->where('id_maestro', optional($u->maestro)->id_maestro);
        }
        return $q;
    }

    public function dashboard(Request $request) {
        $grupos = $this->gruposVisibles($request->user())->get();
        return view('panel.dashboard', ['grupos' => $grupos]);
    }

    public function autorizarGrupo(Usuario $u, Grupo $grupo): void {
        if ($u->esCoordinadorOAdmin()) return;
        abort_unless($grupo->id_maestro === optional($u->maestro)->id_maestro, 403, 'No puedes ver este grupo.');
    }

    public function show(Request $request, Grupo $grupo) {
        $this->autorizarGrupo($request->user(), $grupo);
        $grupo->load(['materia', 'ciclo']);
        $alumnos = $grupo->inscripciones()->with('alumno.usuario')->get();
        $eventos = $grupo->eventos()->with('practica')->get();
        return view('panel.grupo', ['grupo' => $grupo, 'alumnos' => $alumnos, 'eventos' => $eventos]);
    }
}
