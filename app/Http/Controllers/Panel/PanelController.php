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
}
