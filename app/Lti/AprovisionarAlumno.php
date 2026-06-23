<?php
namespace App\Lti;

use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class AprovisionarAlumno {
    public function desdeLaunch(string $ltiUserId, string $nombre, string $apellidos, ?string $correo): Alumno {
        return DB::transaction(function () use ($ltiUserId, $nombre, $apellidos, $correo) {
            $usuario = Usuario::where('lti_user_id', $ltiUserId)->first();
            if ($usuario && $usuario->alumno) {
                return $usuario->alumno;
            }

            $rolAlumno = Rol::firstOrCreate(['nombre' => 'Alumno']);
            $carrera = Carrera::firstOrCreate(
                ['clave' => 'LTI'],
                ['nombre' => 'Externa (LTI)', 'duracion_semestres' => 1]
            );

            if (! $usuario) {
                $correoFinal = $correo ?: "lti+{$ltiUserId}@metaverso.local";
                if (Usuario::where('correo', $correoFinal)->exists()) {
                    $correoFinal = "lti+{$ltiUserId}@metaverso.local";
                }
                $usuario = Usuario::create([
                    'id_rol' => $rolAlumno->id_rol,
                    'correo' => $correoFinal,
                    'nombre' => $nombre ?: 'Alumno',
                    'apellidos' => $apellidos ?: 'LTI',
                    'lti_user_id' => $ltiUserId,
                ]);
            }

            return Alumno::create([
                'id_usuario' => $usuario->id_usuario,
                'id_carrera' => $carrera->id_carrera,
                'matricula' => 'LTI-'.$ltiUserId,
                'semestre_actual' => 1,
                'generacion' => date('Y'),
            ]);
        });
    }
}
