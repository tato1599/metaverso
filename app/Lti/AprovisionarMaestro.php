<?php

namespace App\Lti;

use App\Models\Maestro;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AprovisionarMaestro
{
    public function desdeRoster(string $ltiUserId, string $nombre, string $apellidos, ?string $correo): Maestro
    {
        return DB::transaction(function () use ($ltiUserId, $nombre, $apellidos, $correo) {
            $usuario = Usuario::where('lti_user_id', $ltiUserId)->first();
            if ($usuario && $usuario->maestro) {
                return $usuario->maestro;
            }

            $rolMaestro = Rol::firstOrCreate(['nombre' => 'Maestro']);

            if (! $usuario) {
                $correoFinal = $correo ?: "lti+{$ltiUserId}@metaverso.local";
                while (Usuario::where('correo', $correoFinal)->exists()) {
                    $correoFinal = 'lti+'.$ltiUserId.'-'.Str::lower(Str::random(4)).'@metaverso.local';
                }
                $usuario = Usuario::create([
                    'id_rol' => $rolMaestro->id_rol,
                    'correo' => $correoFinal,
                    'nombre' => $nombre ?: 'Maestro',
                    'apellidos' => $apellidos ?: 'LTI',
                    'lti_user_id' => $ltiUserId,
                ]);
            }

            /*
             * numero_empleado lleva el lti_user_id COMPLETO (enmienda numero-empleado):
             * espejo exacto del precedente `matricula` de AprovisionarAlumno; la columna
             * es varchar(255) unique, sin riesgo de colisión ni pérdida de trazabilidad.
             */
            return Maestro::create([
                'id_usuario' => $usuario->id_usuario,
                'numero_empleado' => 'LTI-'.$ltiUserId,
            ]);
        });
    }
}
