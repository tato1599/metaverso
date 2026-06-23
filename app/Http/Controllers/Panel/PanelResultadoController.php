<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\SesionPractica;
use Illuminate\Http\Request;

class PanelResultadoController extends Controller {
    public function __construct(private PanelController $panel) {}

    public function index(Request $request, Grupo $grupo) {
        $this->panel->autorizarGrupo($request->user(), $grupo);
        $eventoIds = $grupo->eventos()->pluck('id_evento');
        $sesiones = SesionPractica::whereIn('id_evento', $eventoIds)
            ->with(['alumno.usuario', 'practica'])
            ->orderByDesc('fecha_inicio')
            ->get();
        return view('panel.resultados', ['grupo' => $grupo, 'sesiones' => $sesiones]);
    }

    public function sesion(Request $request, SesionPractica $sesion) {
        $sesion->load(['alumno.usuario', 'practica', 'evento']);
        $grupo = Grupo::findOrFail($sesion->evento->id_grupo);
        $this->panel->autorizarGrupo($request->user(), $grupo);
        return view('panel.sesion', ['sesion' => $sesion]);
    }
}
