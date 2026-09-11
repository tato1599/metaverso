<?php

namespace App\Http\Controllers\Lti;

use App\Http\Controllers\Controller;
use App\Lti\AprovisionarAlumno;
use App\Lti\DatosLaunch;
use App\Lti\LaunchValidador;
use App\Models\Alumno;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\LtiContexto;
use App\Models\LtiVinculoActividad;
use App\Models\Practica;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LtiLaunchController extends Controller
{
    public function launch(Request $request, LaunchValidador $validador, AprovisionarAlumno $aprovisionar)
    {
        $datos = $validador->validar($request);

        // ANTES del branch de deep-link: el deep-link también trae claim de contexto
        // y es la primera oportunidad de conocer el curso (enmienda lti-contexto-upsert).
        $this->capturarContexto($datos);

        if ($datos->esDeepLink) {
            session(['lti_issuer' => $datos->issuer, 'lti_launch_id' => $datos->launchId]);

            return redirect()->route('lti.deeplink');
        }

        abort_if(! $datos->idPractica, 422, 'Esta actividad no tiene práctica asignada. Pide al maestro reconfigurarla.');

        $alumno = $aprovisionar->desdeLaunch($datos->ltiUserId, $datos->nombre, $datos->apellidos, $datos->correo);

        // El contexto AGS se guarda ANTES de cualquier redirección: la sesión de
        // juego se creará días después, desde la API, y sin esto la calificación
        // no tendría a dónde volver. Cada launch refresca el vínculo.
        $this->guardarVinculoAgs($datos, $alumno);

        // A partir de aquí el alumno navega por la aplicación, no por un
        // interstitial: necesita sesión web propia.
        Auth::login($alumno->usuario);
        $request->session()->regenerate();

        $grupo = $this->grupoDelContexto($datos);

        if (! $grupo) {
            return Inertia::render('Mi/PracticaNoDisponible', [
                'motivo' => 'curso-sin-grupo',
                'practica' => optional(Practica::find($datos->idPractica))->titulo,
            ]);
        }

        // El launch prueba que el alumno está en ese curso de Moodle. Inscribirlo
        // aquí es lo mismo que hace la sincronización de roster por NRPS, solo que
        // disparado por él y no por el maestro: sin esto, quien entra antes de que
        // el maestro sincronice se topa con un 403.
        Inscripcion::firstOrCreate(
            ['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo],
            ['fecha_inscripcion' => now(), 'estatus' => 'activa'],
        );

        $evento = $this->eventoDestino($alumno->id_alumno, $grupo->id_grupo, $datos->idPractica);

        if (! $evento) {
            return Inertia::render('Mi/PracticaNoDisponible', [
                'motivo' => 'sin-fechas',
                'practica' => optional(Practica::find($datos->idPractica))->titulo,
                'curso' => $grupo->clave,
            ]);
        }

        // La reserva es obligatoria también por aquí: se aterriza en la vista de
        // la práctica, que decide si toca reservar, esperar o entrar a jugar.
        return redirect()->route('mi.eventos.show', $evento->id_evento);
    }

    /** El grupo enlazado al curso de Moodle desde el que se lanzó, si lo hay. */
    private function grupoDelContexto(DatosLaunch $datos): ?Grupo
    {
        if ($datos->ltiPlatformId === null || $datos->contextId === null) {
            return null;
        }

        $contexto = LtiContexto::where('lti_platform_id', $datos->ltiPlatformId)
            ->where('context_id', $datos->contextId)
            ->first();

        return $contexto ? Grupo::where('id_lti_contexto', $contexto->id)->first() : null;
    }

    /**
     * A qué fecha llevarlo: la que ya reservó; si no, la próxima que no ha
     * terminado; y si todas pasaron, la última, para que vea qué ocurrió.
     */
    private function eventoDestino(int $idAlumno, int $idGrupo, int $idPractica): ?EventoAgenda
    {
        $eventos = EventoAgenda::where('id_grupo', $idGrupo)
            ->where('id_practica', $idPractica)
            ->orderBy('fecha_hora_inicio')
            ->get();

        if ($eventos->isEmpty()) {
            return null;
        }

        $reservados = Reserva::where('id_alumno', $idAlumno)
            ->where('estatus', 'activa')
            ->whereIn('id_evento', $eventos->pluck('id_evento'))
            ->pluck('id_evento');

        return $eventos->firstWhere(fn (EventoAgenda $e) => $reservados->contains($e->id_evento))
            ?? $eventos->first(fn (EventoAgenda $e) => $e->fecha_hora_fin->gte(now()) && $e->estatus !== 'cancelado')
            ?? $eventos->last();
    }

    /** Refresca a dónde escribir la calificación de este alumno en esta práctica. */
    private function guardarVinculoAgs(DatosLaunch $datos, Alumno $alumno): void
    {
        if ($datos->agsLineitemUrl === null) {
            return;
        }

        LtiVinculoActividad::updateOrCreate(
            ['id_alumno' => $alumno->id_alumno, 'id_practica' => $datos->idPractica],
            [
                'lti_platform_id' => $datos->ltiPlatformId,
                'ags_lineitem_url' => $datos->agsLineitemUrl,
                'ags_endpoint' => $datos->agsEndpoint,
            ],
        );
    }

    /**
     * Upsert atómico de Postgres sobre unique(lti_platform_id, context_id): dos
     * launches concurrentes del mismo curso no truenan. `nrps_url` solo entra a la
     * lista de actualización cuando viene no-null, para que un deep-link sin claim
     * NRPS no pise un nrps_url ya capturado.
     */
    private function capturarContexto(DatosLaunch $datos): void
    {
        if ($datos->ltiPlatformId === null || $datos->contextId === null) {
            return;
        }

        $columnasActualizar = ['titulo'];
        if ($datos->nrpsUrl !== null) {
            $columnasActualizar[] = 'nrps_url';
        }

        LtiContexto::upsert([[
            'lti_platform_id' => $datos->ltiPlatformId,
            'context_id' => $datos->contextId,
            'titulo' => $datos->contextTitulo,
            'nrps_url' => $datos->nrpsUrl,
        ]], ['lti_platform_id', 'context_id'], $columnasActualizar);
    }
}
