<?php
namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Lti\AprovisionarAlumno;
use App\Lti\LaunchValidador;
use App\Models\SesionPractica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LtiLaunchController extends Controller {
    public function launch(Request $request, LaunchValidador $validador, AprovisionarAlumno $aprovisionar) {
        $datos = $validador->validar($request);

        if ($datos->esDeepLink) {
            session(['lti_issuer' => $datos->issuer, 'lti_launch_id' => $datos->launchId]);
            return redirect()->route('lti.deeplink');
        }

        abort_if(! $datos->idPractica, 422, 'Esta actividad no tiene práctica asignada. Pide al maestro reconfigurarla.');

        $alumno = $aprovisionar->desdeLaunch($datos->ltiUserId, $datos->nombre, $datos->apellidos, $datos->correo);

        $sesion = SesionPractica::create([
            'id_practica' => $datos->idPractica,
            'id_evento' => null,
            'id_alumno' => $alumno->id_alumno,
            'fecha_inicio' => now(),
            'estatus' => 'en_progreso',
            'lti_platform_id' => $datos->ltiPlatformId,
            'ags_lineitem_url' => $datos->agsLineitemUrl,
            'ags_endpoint' => $datos->agsEndpoint,
        ]);

        $token = Str::random(64);
        Cache::put('lti_play_'.hash('sha256', $token), $sesion->id_sesion, now()->addHours(2));
        $scheme = config('metaverso.deeplink_scheme', 'tecnm-metaverso');
        $deeplink = "{$scheme}://play?lti_session_token={$token}";

        return view('lti.abrir-juego', ['deeplink' => $deeplink, 'practicaId' => $datos->idPractica]);
    }
}
