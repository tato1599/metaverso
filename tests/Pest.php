<?php

use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\CicloEscolar;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Practica;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

function crearEventoBasico(): EventoAgenda
{
    $rol = Rol::create(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $u->id_usuario, 'numero_empleado' => 'E1']);
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $prac = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab 1']);

    return EventoAgenda::create([
        'id_practica' => $prac->id_practica, 'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(),
    ]);
}

/*
 * Escenario compartido de agenda: grupo con maestro, práctica, un evento y un
 * alumno inscrito. Vive aquí y no dentro de un archivo de pruebas para que cada
 * archivo se pueda correr por separado con --filter.
 */
/**
 * @return array{usuario: Usuario, alumno: Alumno, grupo: Grupo, evento: EventoAgenda}
 */
function reservarEscenario(array $eventoAttrs = [], ?string $estatusInscripcion = 'activa'): array
{
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $uM = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $uM->id_usuario, 'numero_empleado' => fake()->unique()->numerify('EMP####')]);
    $materia = Materia::create(['clave' => fake()->unique()->bothify('MAT-###'), 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::firstOrCreate(['nombre' => '2026-1'], ['fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-06-15', 'activo' => true]);
    $grupo = Grupo::create(['id_materia' => $materia->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $practica = Practica::create(['id_materia' => $materia->id_materia, 'titulo' => 'P1', 'orden' => 1, 'escena_referencia' => 'Lab_1']);
    $evento = EventoAgenda::create(array_merge([
        'id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now()->addDay(), 'fecha_hora_fin' => now()->addDay()->addHour(),
        'estatus' => 'programado', 'cupo_maximo' => 5,
    ], $eventoAttrs));

    [$usuario, $alumno] = reservarNuevoAlumno($grupo, $estatusInscripcion);

    return ['usuario' => $usuario, 'alumno' => $alumno, 'grupo' => $grupo, 'evento' => $evento];
}

/**
 * @return array{0: Usuario, 1: Alumno}
 */
function reservarNuevoAlumno(Grupo $grupo, ?string $estatusInscripcion = 'activa'): array
{
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $u = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'A', 'apellidos' => 'L', 'activo' => true]);
    $carrera = Carrera::firstOrCreate(['clave' => 'ISC'], ['nombre' => 'ISC', 'duracion_semestres' => 9]);
    $alumno = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => fake()->unique()->numerify('2025####'), 'semestre_actual' => 3, 'generacion' => '2025']);
    if ($estatusInscripcion !== null) {
        Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => $estatusInscripcion]);
    }

    return [$u, $alumno];
}
