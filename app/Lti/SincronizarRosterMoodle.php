<?php

namespace App\Lti;

use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/*
 * NOTA de identidad por plataforma (enmienda F8): lti_user_id, matricula y
 * numero_empleado son columnas GLOBALES, pero el `sub` que manda Moodle es único
 * solo POR plataforma. Antes de activar una segunda plataforma LTI hay que
 * scopear la identidad LTI por lti_platform_id (p. ej. unique(lti_platform_id,
 * lti_user_id)) para evitar colisiones entre plataformas. Sin código en F6.
 */
class SincronizarRosterMoodle
{
    public function __construct(
        private RosterCliente $roster,
        private AprovisionarAlumno $aprovisionarAlumno,
        private AprovisionarMaestro $aprovisionarMaestro,
    ) {}

    /**
     * Sync upsert-only: crea/reactiva usuarios, alumnos, maestros e inscripciones.
     * Los locales que ya no vienen en el roster se REPORTAN, jamás se dan de baja.
     *
     * @return array{
     *     alumnos_nuevos: int,
     *     alumnos_reactivados: int,
     *     maestros: int,
     *     sin_cambio: int,
     *     inactivos: list<string>,
     *     en_moodle_no_locales: list<string>,
     *     locales_no_en_moodle: list<string>
     * }
     */
    public function sincronizar(Grupo $grupo): array
    {
        $contexto = $grupo->ltiContexto;
        if ($contexto === null) {
            throw ValidationException::withMessages(['sincronizar' => 'El grupo no tiene un curso de Moodle vinculado.']);
        }
        if (blank($contexto->nrps_url)) {
            throw ValidationException::withMessages(['sincronizar' => 'El curso vinculado no tiene servicio de roster (NRPS). Habilita "IMS LTI Names and Role Provisioning" en la herramienta de Moodle y vuelve a lanzar la actividad.']);
        }

        try {
            $members = $this->roster->getMembers($grupo);
        } catch (\Throwable $e) {
            Log::warning('NRPS: error al obtener el roster de Moodle', [
                'id_grupo' => $grupo->id_grupo,
                'error' => $e->getMessage(),
            ]);
            throw ValidationException::withMessages(['sincronizar' => 'Moodle rechazó la consulta del roster: '.$e->getMessage()]);
        }

        $resumen = [
            'alumnos_nuevos' => 0,
            'alumnos_reactivados' => 0,
            'maestros' => 0,
            'sin_cambio' => 0,
            'inactivos' => [],
            'en_moodle_no_locales' => [],
            'locales_no_en_moodle' => [],
        ];
        $idsEnMoodle = [];

        foreach ($members as $member) {
            $ltiUserId = (string) ($member['user_id'] ?? '');
            if ($ltiUserId === '') {
                continue;
            }
            $idsEnMoodle[] = $ltiUserId;
            $etiqueta = $this->etiqueta($member, $ltiUserId);

            // Solo members Active se upsertean; Inactive/Deleted van al reporte (enmienda F7).
            if (($member['status'] ?? 'Active') !== 'Active') {
                $resumen['inactivos'][] = $etiqueta;

                continue;
            }

            $roles = (array) ($member['roles'] ?? []);
            $esInstructor = $this->tieneRol($roles, 'Instructor');
            $esLearner = $this->tieneRol($roles, 'Learner');
            if (! $esInstructor && ! $esLearner) {
                continue;
            }

            if (! Usuario::where('lti_user_id', $ltiUserId)->exists()) {
                $resumen['en_moodle_no_locales'][] = $etiqueta;
            }

            $nombre = (string) ($member['given_name'] ?? ($member['name'] ?? ''));
            $apellidos = (string) ($member['family_name'] ?? '');
            $correo = $member['email'] ?? null;

            // Member con ambos roles: Instructor gana y no se inscribe como alumno (enmienda F7).
            if ($esInstructor) {
                $this->aprovisionarMaestro->desdeRoster($ltiUserId, $nombre, $apellidos, $correo);
                $resumen['maestros']++;

                continue;
            }

            $alumno = $this->aprovisionarAlumno->desdeLaunch($ltiUserId, $nombre, $apellidos, $correo);

            /*
             * El unique (id_alumno, id_grupo) de BD es incondicional: si existe fila
             * en baja se reactiva; solo se crea cuando el par no existe (lógica de F4).
             */
            $inscripcion = Inscripcion::where('id_alumno', $alumno->id_alumno)
                ->where('id_grupo', $grupo->id_grupo)
                ->first();

            if ($inscripcion === null) {
                Inscripcion::create([
                    'id_alumno' => $alumno->id_alumno,
                    'id_grupo' => $grupo->id_grupo,
                    'fecha_inscripcion' => today(),
                    'estatus' => 'activa',
                ]);
                $resumen['alumnos_nuevos']++;
            } elseif ($inscripcion->estatus !== 'activa') {
                $inscripcion->update(['estatus' => 'activa', 'fecha_inscripcion' => today()]);
                $resumen['alumnos_reactivados']++;
            } else {
                $resumen['sin_cambio']++;
            }
        }

        $inscripcionesActivas = Inscripcion::where('id_grupo', $grupo->id_grupo)
            ->where('estatus', 'activa')
            ->with('alumno.usuario')
            ->get();
        foreach ($inscripcionesActivas as $inscripcion) {
            $usuario = $inscripcion->alumno?->usuario;
            if ($usuario?->lti_user_id === null || ! in_array($usuario->lti_user_id, $idsEnMoodle, true)) {
                $resumen['locales_no_en_moodle'][] = $usuario
                    ? trim($usuario->nombre.' '.$usuario->apellidos)
                    : "alumno #{$inscripcion->id_alumno}";
            }
        }

        return $resumen;
    }

    /**
     * Acepta la URI completa de LIS ('...membership#Instructor'), la forma corta
     * ('Instructor') y el fallback institucional ('...institution/person#Instructor').
     *
     * @param  list<string>  $roles
     */
    private function tieneRol(array $roles, string $rol): bool
    {
        foreach ($roles as $r) {
            $r = (string) $r;
            if ($r === $rol
                || str_contains($r, 'membership#'.$rol)
                || str_contains($r, 'institution/person#'.$rol)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $member
     */
    private function etiqueta(array $member, string $ltiUserId): string
    {
        $nombre = trim((string) ($member['name'] ?? trim(((string) ($member['given_name'] ?? '')).' '.((string) ($member['family_name'] ?? '')))));

        return $nombre !== '' ? $nombre : (string) ($member['email'] ?? $ltiUserId);
    }
}
