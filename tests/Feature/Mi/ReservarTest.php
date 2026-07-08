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
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

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

it('reserva el slot y regresa con éxito', function () {
    $e = reservarEscenario();
    $this->actingAs($e['usuario'])
        ->from('/mi/calendario')
        ->post('/mi/reservas', ['id_evento' => $e['evento']->id_evento])
        ->assertRedirect('/mi/calendario');

    expect($e['evento']->reservasActivas()->count())->toBe(1);
});

it('valida id_evento ausente (422) e inexistente (404)', function () {
    $e = reservarEscenario();
    $this->actingAs($e['usuario'])->postJson('/mi/reservas', [])->assertStatus(422);
    $this->actingAs($e['usuario'])->postJson('/mi/reservas', ['id_evento' => 99999])->assertNotFound();
});

it('rechaza al alumno sin inscripción en el grupo', function () {
    $e = reservarEscenario(estatusInscripcion: null);
    $this->actingAs($e['usuario'])
        ->post('/mi/reservas', ['id_evento' => $e['evento']->id_evento])
        ->assertForbidden();
});

it('rechaza al alumno con inscripción en baja', function () {
    $e = reservarEscenario(estatusInscripcion: 'baja');
    $this->actingAs($e['usuario'])
        ->post('/mi/reservas', ['id_evento' => $e['evento']->id_evento])
        ->assertForbidden();
});

it('rechaza slot lleno', function () {
    $e = reservarEscenario(['cupo_maximo' => 1]);
    [, $otro] = reservarNuevoAlumno($e['grupo']);
    Reserva::create(['id_evento' => $e['evento']->id_evento, 'id_alumno' => $otro->id_alumno]);

    $this->actingAs($e['usuario'])
        ->postJson('/mi/reservas', ['id_evento' => $e['evento']->id_evento])
        ->assertStatus(422)->assertJsonValidationErrors('evento');
});

it('rechaza reserva duplicada', function () {
    $e = reservarEscenario();
    Reserva::create(['id_evento' => $e['evento']->id_evento, 'id_alumno' => $e['alumno']->id_alumno]);

    $this->actingAs($e['usuario'])
        ->postJson('/mi/reservas', ['id_evento' => $e['evento']->id_evento])
        ->assertStatus(422)->assertJsonValidationErrors('evento');
});

it('rechaza evento cancelado y evento pasado', function () {
    $cancelado = reservarEscenario(['estatus' => 'cancelado']);
    $this->actingAs($cancelado['usuario'])
        ->postJson('/mi/reservas', ['id_evento' => $cancelado['evento']->id_evento])
        ->assertStatus(422);

    $pasado = reservarEscenario([
        'fecha_hora_inicio' => now()->subHours(2),
        'fecha_hora_fin' => now()->subHour(),
    ]);
    $this->actingAs($pasado['usuario'])
        ->postJson('/mi/reservas', ['id_evento' => $pasado['evento']->id_evento])
        ->assertStatus(422);
});

it('el último lugar se asigna una sola vez', function () {
    $e = reservarEscenario(['cupo_maximo' => 2]);
    [$u2] = reservarNuevoAlumno($e['grupo']);
    [$u3] = reservarNuevoAlumno($e['grupo']);

    $this->actingAs($e['usuario'])->post('/mi/reservas', ['id_evento' => $e['evento']->id_evento])->assertRedirect();
    $this->actingAs($u2)->post('/mi/reservas', ['id_evento' => $e['evento']->id_evento])->assertRedirect();
    $this->actingAs($u3)->postJson('/mi/reservas', ['id_evento' => $e['evento']->id_evento])
        ->assertStatus(422)->assertJsonValidationErrors('evento');

    expect($e['evento']->reservasActivas()->count())->toBe(2);
});

it('cancela la propia reserva antes del inicio', function () {
    $e = reservarEscenario();
    $reserva = Reserva::create(['id_evento' => $e['evento']->id_evento, 'id_alumno' => $e['alumno']->id_alumno]);

    $this->actingAs($e['usuario'])->delete("/mi/reservas/{$reserva->id_reserva}")->assertRedirect();
    expect($reserva->fresh()->estatus)->toBe('cancelada');
});

it('no permite cancelar una reserva ajena', function () {
    $e = reservarEscenario();
    [, $otro] = reservarNuevoAlumno($e['grupo']);
    $ajena = Reserva::create(['id_evento' => $e['evento']->id_evento, 'id_alumno' => $otro->id_alumno]);

    $this->actingAs($e['usuario'])->delete("/mi/reservas/{$ajena->id_reserva}")->assertForbidden();
    expect($ajena->fresh()->estatus)->toBe('activa');
});

it('no permite cancelar cuando el evento ya inició', function () {
    $e = reservarEscenario([
        'fecha_hora_inicio' => now()->subMinutes(10),
        'fecha_hora_fin' => now()->addHour(),
    ]);
    $reserva = Reserva::create(['id_evento' => $e['evento']->id_evento, 'id_alumno' => $e['alumno']->id_alumno]);

    $this->actingAs($e['usuario'])->deleteJson("/mi/reservas/{$reserva->id_reserva}")->assertStatus(422);
    expect($reserva->fresh()->estatus)->toBe('activa');
});

it('permite re-reservar tras cancelar', function () {
    $e = reservarEscenario();
    $reserva = Reserva::create(['id_evento' => $e['evento']->id_evento, 'id_alumno' => $e['alumno']->id_alumno]);
    $reserva->update(['estatus' => 'cancelada']);

    $this->actingAs($e['usuario'])
        ->post('/mi/reservas', ['id_evento' => $e['evento']->id_evento])
        ->assertRedirect();
    expect($e['evento']->reservasActivas()->count())->toBe(1);
});

it('bloquea a un maestro en las rutas de reservas', function () {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $uM = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'M', 'apellidos' => 'X']);

    $this->actingAs($uM)->post('/mi/reservas', ['id_evento' => 1])->assertForbidden();
});
