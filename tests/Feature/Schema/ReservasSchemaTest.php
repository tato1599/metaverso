<?php

use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\CicloEscolar;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Practica;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function armarEvento(array $attrs = []): EventoAgenda
{
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $u->id_usuario, 'numero_empleado' => fake()->unique()->numerify('EMP###')]);
    $materia = Materia::create(['clave' => fake()->unique()->bothify('MAT-###'), 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-06-15', 'activo' => true]);
    $grupo = Grupo::create(['id_materia' => $materia->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $practica = Practica::create(['id_materia' => $materia->id_materia, 'titulo' => 'P1', 'orden' => 1, 'escena_referencia' => 'Lab_1']);

    return EventoAgenda::create(array_merge([
        'id_practica' => $practica->id_practica,
        'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now()->addDay(),
        'fecha_hora_fin' => now()->addDay()->addHour(),
        'estatus' => 'programado',
        'cupo_maximo' => 5,
    ], $attrs));
}

function armarAlumno(): Alumno
{
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $u = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'A', 'apellidos' => 'L']);
    $carrera = Carrera::firstOrCreate(['clave' => 'ISC'], ['nombre' => 'ISC', 'duracion_semestres' => 9]);

    return Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => fake()->unique()->numerify('2025####'), 'semestre_actual' => 3, 'generacion' => '2025']);
}

it('eventos_agenda tiene cupo_maximo', function () {
    expect(Schema::hasColumn('eventos_agenda', 'cupo_maximo'))->toBeTrue();
    expect(armarEvento()->cupo_maximo)->toBe(5);
});

it('crea reservas y las relaciones navegan', function () {
    $evento = armarEvento();
    $alumno = armarAlumno();
    $r = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
    expect($r->estatus)->toBe('activa')
        ->and($r->evento->id_evento)->toBe($evento->id_evento)
        ->and($r->alumno->id_alumno)->toBe($alumno->id_alumno)
        ->and($evento->reservasActivas()->count())->toBe(1);
});

it('el índice parcial único impide dos reservas activas del mismo alumno en el mismo evento', function () {
    $evento = armarEvento();
    $alumno = armarAlumno();
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
    // Sin asserts de BD después del toThrow: la violación 23505 aborta la transacción de Postgres.
    expect(fn () => Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('permite re-reservar tras cancelar', function () {
    $evento = armarEvento();
    $alumno = armarAlumno();
    $r1 = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
    $r1->update(['estatus' => 'cancelada']);
    $r2 = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
    expect($r2->estatus)->toBe('activa')->and($evento->reservasActivas()->count())->toBe(1);
});
