<?php

namespace App\Console\Commands;

use App\Lti\SincronizarRosterMoodle;
use App\Models\Grupo;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class SincronizarRosterCommand extends Command
{
    protected $signature = 'metaverso:sincronizar-roster {id_grupo}';

    protected $description = 'Sincroniza el roster del grupo desde Moodle (LTI NRPS)';

    public function handle(SincronizarRosterMoodle $sincronizador): int
    {
        $grupo = Grupo::find($this->argument('id_grupo'));
        if (! $grupo) {
            $this->error('Grupo no encontrado.');

            return self::FAILURE;
        }

        try {
            $resumen = $sincronizador->sincronizar($grupo);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Alumnos nuevos: %d | reactivados: %d | maestros: %d | sin cambio: %d',
            $resumen['alumnos_nuevos'],
            $resumen['alumnos_reactivados'],
            $resumen['maestros'],
            $resumen['sin_cambio'],
        ));

        $listas = [
            'inactivos' => 'Inactivos en Moodle (omitidos)',
            'en_moodle_no_locales' => 'Creados desde Moodle',
            'locales_no_en_moodle' => 'Solo locales, ya no en Moodle (revisar)',
        ];
        foreach ($listas as $clave => $titulo) {
            if ($resumen[$clave] !== []) {
                $this->line($titulo.': '.implode(', ', $resumen[$clave]));
            }
        }

        return self::SUCCESS;
    }
}
