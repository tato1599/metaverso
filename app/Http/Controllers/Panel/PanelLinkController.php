<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PanelLinkController extends Controller
{
    public function __construct(private MagicLinkService $magicLink, private PanelController $panel) {}

    /**
     * @return array<int, array{nombre: string, matricula: string, url: string}>
     */
    private function filasDeLinks(Grupo $grupo, EventoAgenda $evento): array
    {
        $inscripciones = $grupo->inscripciones()->with('alumno.usuario')->get();
        $filas = [];
        foreach ($inscripciones as $insc) {
            $alumno = $insc->alumno;
            if (! $alumno || ! $alumno->usuario) {
                continue;
            }
            $res = $this->magicLink->generar($alumno->id_usuario, $evento->id_evento);
            $filas[] = [
                'nombre' => trim($alumno->usuario->nombre.' '.$alumno->usuario->apellidos),
                'matricula' => $alumno->matricula,
                'url' => $res['url'],
            ];
        }

        return $filas;
    }

    public function generar(Request $request, Grupo $grupo, EventoAgenda $evento)
    {
        $this->panel->autorizarGrupo($request->user(), $grupo);
        abort_unless($evento->id_grupo === $grupo->id_grupo, 404);
        $filas = $this->filasDeLinks($grupo, $evento);

        return Inertia::render('Panel/Links', [
            'grupo' => [
                'id_grupo' => $grupo->id_grupo,
                'clave' => $grupo->clave,
            ],
            'filas' => $filas,
        ]);
    }
}
