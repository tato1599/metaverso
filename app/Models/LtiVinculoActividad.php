<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dónde escribir la calificación de un alumno en una práctica, según el último
 * launch LTI que hizo. Ver la migración para el porqué.
 */
class LtiVinculoActividad extends Model
{
    protected $table = 'lti_vinculos_actividad';

    protected $guarded = [];

    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'id_alumno');
    }

    public function practica()
    {
        return $this->belongsTo(Practica::class, 'id_practica');
    }

    /**
     * Los datos AGS que hay que estampar en una sesión nueva, o null si este
     * alumno nunca entró a esta práctica desde Moodle.
     *
     * @return array{lti_platform_id: int|null, ags_lineitem_url: string|null, ags_endpoint: string|null}|null
     */
    public static function paraSesion(int $idAlumno, int $idPractica): ?array
    {
        $vinculo = static::where('id_alumno', $idAlumno)
            ->where('id_practica', $idPractica)
            ->first();

        if (! $vinculo || ! $vinculo->ags_lineitem_url) {
            return null;
        }

        return [
            'lti_platform_id' => $vinculo->lti_platform_id,
            'ags_lineitem_url' => $vinculo->ags_lineitem_url,
            'ags_endpoint' => $vinculo->ags_endpoint,
        ];
    }
}
