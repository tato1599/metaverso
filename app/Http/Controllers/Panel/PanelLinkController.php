<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class PanelLinkController extends Controller {
    public function __construct(private MagicLinkService $magicLink, private PanelController $panel) {}

    private function filasDeLinks(Grupo $grupo, EventoAgenda $evento): array {
        $inscripciones = $grupo->inscripciones()->with('alumno.usuario')->get();
        $filas = [];
        foreach ($inscripciones as $insc) {
            $alumno = $insc->alumno;
            if (! $alumno || ! $alumno->usuario) continue;
            $res = $this->magicLink->generar($alumno->id_usuario, $evento->id_evento);
            $filas[] = [
                'nombre' => trim($alumno->usuario->nombre.' '.$alumno->usuario->apellidos),
                'matricula' => $alumno->matricula,
                'url' => $res['url'],
            ];
        }
        return $filas;
    }

    public function generar(Request $request, Grupo $grupo, EventoAgenda $evento) {
        $this->panel->autorizarGrupo($request->user(), $grupo);
        abort_unless($evento->id_grupo === $grupo->id_grupo, 404);
        $filas = $this->filasDeLinks($grupo, $evento);
        return view('panel.links', ['grupo' => $grupo, 'evento' => $evento, 'filas' => $filas]);
    }

    public function csv(Request $request, Grupo $grupo, EventoAgenda $evento) {
        $this->panel->autorizarGrupo($request->user(), $grupo);
        abort_unless($evento->id_grupo === $grupo->id_grupo, 404);
        $filas = $this->filasDeLinks($grupo, $evento);
        $callback = function () use ($filas) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['nombre', 'matricula', 'url']);
            foreach ($filas as $f) fputcsv($out, [$f['nombre'], $f['matricula'], $f['url']]);
            fclose($out);
        };
        return response()->streamDownload($callback, "links-grupo-{$grupo->id_grupo}-evento-{$evento->id_evento}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
