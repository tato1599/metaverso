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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function agendaUsuario(string $rol): Usuario
{
    $r = Rol::firstOrCreate(['nombre' => $rol]);

    return Usuario::create([
        'id_rol' => $r->id_rol,
        'correo' => fake()->unique()->safeEmail(),
        'contrasena_hash' => Hash::make('x'),
        'nombre' => 'T',
        'apellidos' => 'U',
        'activo' => true,
    ]);
}

function agendaMaestro(): Usuario
{
    $u = agendaUsuario('Maestro');
    Maestro::create(['id_usuario' => $u->id_usuario, 'numero_empleado' => fake()->unique()->numerify('EMP####')]);

    return $u->fresh();
}

function agendaGrupoDe(Usuario $uMaestro): Grupo
{
    $materia = Materia::create(['clave' => fake()->unique()->bothify('MAT-###'), 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::firstOrCreate(['nombre' => '2026-1'], ['fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-06-15', 'activo' => true]);

    return Grupo::create([
        'id_materia' => $materia->id_materia,
        'id_maestro' => $uMaestro->maestro->id_maestro,
        'id_ciclo' => $ciclo->id_ciclo,
        'clave' => '3A',
        'cupo_maximo' => 30,
    ]);
}

function agendaPracticaDe(Grupo $grupo): Practica
{
    return Practica::create(['id_materia' => $grupo->id_materia, 'titulo' => 'P1', 'orden' => 1, 'escena_referencia' => 'Lab_1']);
}

function agendaEventoEn(Grupo $grupo, array $attrs = []): EventoAgenda
{
    return EventoAgenda::create(array_merge([
        'id_practica' => agendaPracticaDe($grupo)->id_practica,
        'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now()->addDay()->setTime(10, 0),
        'fecha_hora_fin' => now()->addDay()->setTime(11, 0),
        'estatus' => 'programado',
        'cupo_maximo' => 5,
    ], $attrs));
}

function agendaAlumnoInscrito(Grupo $grupo): Alumno
{
    $u = agendaUsuario('Alumno');
    $carrera = Carrera::firstOrCreate(['clave' => 'ISC'], ['nombre' => 'ISC', 'duracion_semestres' => 9]);
    $alumno = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => fake()->unique()->numerify('2025####'), 'semestre_actual' => 3, 'generacion' => '2025']);
    Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);

    return $alumno;
}

it('agenda: invitado redirige a login y alumno recibe 403', function () {
    $this->get('/panel/agenda')->assertRedirect('/login');
    $this->actingAs(agendaUsuario('Alumno'))->get('/panel/agenda')->assertForbidden();
});

it('maestro ve solo eventos de sus grupos; coordinador ve todos', function () {
    $m1 = agendaMaestro();
    $m2 = agendaMaestro();
    agendaEventoEn(agendaGrupoDe($m1));
    agendaEventoEn(agendaGrupoDe($m2));

    $this->actingAs($m1)->get('/panel/agenda')->assertInertia(
        fn (Assert $page) => $page->component('Panel/Agenda')->has('eventos', 1)
    );
    $this->actingAs(agendaUsuario('Coordinador'))->get('/panel/agenda')->assertInertia(
        fn (Assert $page) => $page->component('Panel/Agenda')->has('eventos', 2)
    );
});

it('maestro crea evento válido', function () {
    $m = agendaMaestro();
    $grupo = agendaGrupoDe($m);
    $practica = agendaPracticaDe($grupo);

    $this->actingAs($m)->post('/panel/eventos', [
        'id_grupo' => $grupo->id_grupo,
        'id_practica' => $practica->id_practica,
        'fecha_hora_inicio' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
        'fecha_hora_fin' => now()->addDays(2)->setTime(11, 0)->toDateTimeString(),
        'cupo_maximo' => 5,
    ])->assertRedirect(route('panel.agenda'));

    $evento = EventoAgenda::latest('id_evento')->first();
    expect($evento->estatus)->toBe('programado')->and($evento->cupo_maximo)->toBe(5);
});

it('rechaza práctica de otra materia', function () {
    $m = agendaMaestro();
    $grupo = agendaGrupoDe($m);
    $otroGrupo = agendaGrupoDe($m);
    $practicaAjena = agendaPracticaDe($otroGrupo);

    $this->actingAs($m)->postJson('/panel/eventos', [
        'id_grupo' => $grupo->id_grupo,
        'id_practica' => $practicaAjena->id_practica,
        'fecha_hora_inicio' => now()->addDays(2)->toDateTimeString(),
        'fecha_hora_fin' => now()->addDays(2)->addHour()->toDateTimeString(),
        'cupo_maximo' => 5,
    ])->assertStatus(422)->assertJsonValidationErrors('id_practica');
});

it('rechaza fin antes del inicio y cupo cero', function () {
    $m = agendaMaestro();
    $grupo = agendaGrupoDe($m);
    $practica = agendaPracticaDe($grupo);
    $base = [
        'id_grupo' => $grupo->id_grupo,
        'id_practica' => $practica->id_practica,
        'fecha_hora_inicio' => now()->addDays(2)->toDateTimeString(),
        'fecha_hora_fin' => now()->addDays(2)->addHour()->toDateTimeString(),
        'cupo_maximo' => 5,
    ];

    $this->actingAs($m)->postJson('/panel/eventos', array_merge($base, [
        'fecha_hora_fin' => now()->addDays(2)->subHour()->toDateTimeString(),
    ]))->assertStatus(422)->assertJsonValidationErrors('fecha_hora_fin');

    $this->actingAs($m)->postJson('/panel/eventos', array_merge($base, ['cupo_maximo' => 0]))
        ->assertStatus(422)->assertJsonValidationErrors('cupo_maximo');
});

it('rechaza crear en grupo ajeno; coordinador sí puede', function () {
    $duenio = agendaMaestro();
    $intruso = agendaMaestro();
    $grupo = agendaGrupoDe($duenio);
    $practica = agendaPracticaDe($grupo);
    $payload = [
        'id_grupo' => $grupo->id_grupo,
        'id_practica' => $practica->id_practica,
        'fecha_hora_inicio' => now()->addDays(2)->toDateTimeString(),
        'fecha_hora_fin' => now()->addDays(2)->addHour()->toDateTimeString(),
        'cupo_maximo' => 5,
    ];

    $this->actingAs($intruso)->post('/panel/eventos', $payload)->assertForbidden();
    $this->actingAs(agendaUsuario('Coordinador'))->post('/panel/eventos', $payload)->assertRedirect(route('panel.agenda'));
});

it('IDOR: detalle, PUT y DELETE de evento ajeno dan 403; coordinador pasa', function () {
    $duenio = agendaMaestro();
    $intruso = agendaMaestro();
    $evento = agendaEventoEn(agendaGrupoDe($duenio));
    $cambio = [
        'fecha_hora_inicio' => now()->addDays(3)->toDateTimeString(),
        'fecha_hora_fin' => now()->addDays(3)->addHour()->toDateTimeString(),
        'cupo_maximo' => 5,
    ];

    $this->actingAs($intruso)->get("/panel/eventos/{$evento->id_evento}")->assertForbidden();
    $this->actingAs($intruso)->put("/panel/eventos/{$evento->id_evento}", $cambio)->assertForbidden();
    $this->actingAs($intruso)->delete("/panel/eventos/{$evento->id_evento}")->assertForbidden();

    $coord = agendaUsuario('Coordinador');
    $this->actingAs($coord)->get("/panel/eventos/{$evento->id_evento}")->assertOk();
    $this->actingAs($coord)->put("/panel/eventos/{$evento->id_evento}", $cambio)->assertRedirect();
    $this->actingAs($coord)->delete("/panel/eventos/{$evento->id_evento}")->assertRedirect(route('panel.agenda'));
});

it('PUT no puede dejar el cupo debajo de las reservas activas', function () {
    $m = agendaMaestro();
    $grupo = agendaGrupoDe($m);
    $evento = agendaEventoEn($grupo);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => agendaAlumnoInscrito($grupo)->id_alumno]);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => agendaAlumnoInscrito($grupo)->id_alumno]);

    $this->actingAs($m)->putJson("/panel/eventos/{$evento->id_evento}", [
        'fecha_hora_inicio' => $evento->fecha_hora_inicio->toDateTimeString(),
        'fecha_hora_fin' => $evento->fecha_hora_fin->toDateTimeString(),
        'cupo_maximo' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('cupo_maximo');
});

it('PUT ignora campos fuera de la whitelist', function () {
    $m = agendaMaestro();
    $grupo = agendaGrupoDe($m);
    $otroGrupo = agendaGrupoDe($m);
    $evento = agendaEventoEn($grupo);
    $practicaOriginal = $evento->id_practica;

    $this->actingAs($m)->put("/panel/eventos/{$evento->id_evento}", [
        'fecha_hora_inicio' => $evento->fecha_hora_inicio->toDateTimeString(),
        'fecha_hora_fin' => $evento->fecha_hora_fin->toDateTimeString(),
        'cupo_maximo' => 8,
        'id_grupo' => $otroGrupo->id_grupo,
        'id_practica' => agendaPracticaDe($otroGrupo)->id_practica,
        'estatus' => 'finalizado',
    ])->assertRedirect();

    $evento->refresh();
    expect($evento->id_grupo)->toBe($grupo->id_grupo)
        ->and($evento->id_practica)->toBe($practicaOriginal)
        ->and($evento->estatus)->toBe('programado')
        ->and($evento->cupo_maximo)->toBe(8);
});

it('DELETE cancela; repetir da 409; PUT sobre cancelado da 409', function () {
    $m = agendaMaestro();
    $evento = agendaEventoEn(agendaGrupoDe($m));

    $this->actingAs($m)->delete("/panel/eventos/{$evento->id_evento}")->assertRedirect(route('panel.agenda'));
    expect($evento->fresh()->estatus)->toBe('cancelado');

    $this->actingAs($m)->delete("/panel/eventos/{$evento->id_evento}")->assertStatus(409);
    $this->actingAs($m)->put("/panel/eventos/{$evento->id_evento}", [
        'fecha_hora_inicio' => now()->addDays(3)->toDateTimeString(),
        'fecha_hora_fin' => now()->addDays(3)->addHour()->toDateTimeString(),
        'cupo_maximo' => 5,
    ])->assertStatus(409);
});

it('el detalle incluye las reservas activas con nombre y matrícula', function () {
    $m = agendaMaestro();
    $grupo = agendaGrupoDe($m);
    $evento = agendaEventoEn($grupo);
    $alumno = agendaAlumnoInscrito($grupo);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => agendaAlumnoInscrito($grupo)->id_alumno, 'estatus' => 'cancelada']);

    $this->actingAs($m)->get("/panel/eventos/{$evento->id_evento}")->assertInertia(
        fn (Assert $page) => $page->component('Panel/EventoDetalle')
            ->has('reservas', 1)
            ->where('reservas.0.matricula', $alumno->matricula)
            ->where('evento.inicio_local', $evento->fecha_hora_inicio->format('Y-m-d\TH:i'))
    );
});

it('semana inválida no revienta', function () {
    $this->actingAs(agendaMaestro())->get('/panel/agenda?semana=basura')->assertOk();
});
