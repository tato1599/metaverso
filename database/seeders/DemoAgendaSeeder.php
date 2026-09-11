<?php

namespace Database\Seeders;

use App\Models\Alumno;
use App\Models\CicloEscolar;
use App\Models\Espacio;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Practica;
use App\Models\Reserva;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Enriquece la demo hasta que las pantallas se vean con varios registros: un
 * segundo grupo, varias prácticas y una agenda repartida entre semanas, con
 * reservas de distintos alumnos para que los cupos no salgan todos vacíos.
 *
 * Es IDEMPOTENTE a propósito (`firstOrCreate` en todo): se puede correr sobre
 * una base ya sembrada sin borrarla, que es justo lo que hace falta cuando la
 * instancia local ya tiene usuarios y contextos creados por launches de Moodle.
 *
 * Las fechas son relativas a `now()`, así que la demo nunca queda en el pasado.
 */
class DemoAgendaSeeder extends Seeder
{
    public function run(): void
    {
        $maestro = Maestro::first();
        $ciclo = CicloEscolar::where('activo', true)->first() ?? CicloEscolar::first();
        $espacio = Espacio::first();

        if (! $maestro || ! $ciclo) {
            $this->command?->warn('DemoAgendaSeeder: falta el demo base. Corre DemoSeeder primero.');

            return;
        }

        $programacion = Materia::where('nombre', 'Programación')->first()
            ?? Materia::firstOrCreate(['clave' => 'SCD-1027'], ['nombre' => 'Programación', 'creditos' => 5]);

        $bases = Materia::firstOrCreate(
            ['clave' => 'AEF-1031'],
            ['nombre' => 'Fundamentos de Bases de Datos', 'creditos' => 4],
        );

        $grupoProg = Grupo::where('clave', '3A')->first();
        $grupoBases = Grupo::firstOrCreate(
            ['clave' => '4B', 'id_ciclo' => $ciclo->id_ciclo],
            ['id_materia' => $bases->id_materia, 'id_maestro' => $maestro->id_maestro, 'cupo_maximo' => 30],
        );

        // Todos los alumnos del demo entran también al segundo grupo: así "Tus
        // grupos" muestra más de uno y los cupos del segundo no salen vacíos.
        $alumnos = Alumno::with('usuario')->get();
        foreach ($alumnos as $alumno) {
            Inscripcion::firstOrCreate(
                ['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupoBases->id_grupo],
                ['fecha_inscripcion' => now(), 'estatus' => 'activa'],
            );
        }

        $practicas = collect([
            [$programacion, 'Práctica 2: Condicionales', 2, 'condicionales', 45],
            [$programacion, 'Práctica 3: Ciclos y arreglos', 3, 'ciclos', 30],
            [$bases, 'Práctica 1: Modelo entidad-relación', 1, 'modelo_er', 60],
            [$bases, 'Práctica 2: Normalización', 2, 'normalizacion', 45],
            [$bases, 'Práctica 3: Consultas SQL', 3, 'consultas_sql', 30],
        ])->map(fn (array $p) => Practica::firstOrCreate(
            ['id_materia' => $p[0]->id_materia, 'titulo' => $p[1]],
            ['orden' => $p[2], 'escena_referencia' => $p[3], 'duracion_estimada' => $p[4]],
        ));

        // (práctica, grupo, días desde hoy, hora, duración en minutos)
        $agenda = [
            [$practicas[0], $grupoProg, 3, 11, 135],
            [$practicas[1], $grupoProg, 6, 9, 120],
            [$practicas[1], $grupoProg, 9, 16, 60],
            [$practicas[2], $grupoBases, 2, 10, 180],
            [$practicas[3], $grupoBases, 5, 13, 135],
            [$practicas[4], $grupoBases, 8, 8, 90],
            [$practicas[4], $grupoBases, 11, 17, 90],
        ];

        $eventos = collect($agenda)->map(function (array $fila) use ($espacio) {
            [$practica, $grupo, $dias, $hora, $minutos] = $fila;
            if (! $grupo) {
                return null;
            }

            $inicio = Carbon::now()->addDays($dias)->setTime($hora, 0);

            return EventoAgenda::firstOrCreate(
                ['id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo, 'fecha_hora_inicio' => $inicio],
                [
                    'id_espacio' => optional($espacio)->id_espacio,
                    'fecha_hora_fin' => $inicio->copy()->addMinutes($minutos),
                    'estatus' => 'programado',
                    'cupo_maximo' => 5,
                ],
            );
        })->filter();

        $this->sembrarReservas($eventos, $alumnos);

        $this->command?->info("DemoAgendaSeeder: {$eventos->count()} eventos y ".Reserva::count().' reservas en total.');
    }

    /**
     * Reserva a los alumnos en algunos eventos, respetando la regla del dominio:
     * una sola reserva activa por práctica y por alumno.
     *
     * @param  Collection<int,EventoAgenda>  $eventos
     * @param  Collection<int,Alumno>  $alumnos
     */
    private function sembrarReservas($eventos, $alumnos): void
    {
        foreach ($eventos->values() as $i => $evento) {
            // Deja algunos eventos sin reservar: son los "te falta reservar".
            if ($i % 3 === 2) {
                continue;
            }

            $slots = $evento->slots();

            foreach ($alumnos->values() as $j => $alumno) {
                // Escalona quién reserva qué, para que los cupos no salgan iguales.
                if (($i + $j) % 3 === 0) {
                    continue;
                }

                $yaTiene = Reserva::where('id_alumno', $alumno->id_alumno)
                    ->where('estatus', 'activa')
                    ->whereHas('evento', fn ($q) => $q
                        ->where('id_practica', $evento->id_practica)
                        ->where('fecha_hora_fin', '>=', now())
                    )
                    ->exists();

                if ($yaTiene) {
                    continue;
                }

                Reserva::firstOrCreate([
                    'id_evento' => $evento->id_evento,
                    'id_alumno' => $alumno->id_alumno,
                    'inicio_slot' => $slots[$j % count($slots)]['inicio'],
                ], ['estatus' => 'activa']);
            }
        }
    }
}
