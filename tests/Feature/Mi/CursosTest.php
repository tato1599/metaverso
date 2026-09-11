<?php

use App\Models\Practica;
use App\Models\Reserva;
use App\Models\SesionPractica;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * El expediente es SOLO LECTURA: lo que se prueba es que el estado de cada
 * práctica lo decide el backend y que no miente sobre el tiempo — una reserva
 * cuya fecha ya pasó no es un plan.
 */

it('el índice lista los cursos con su avance, sin volcar las prácticas', function () {
    $e = reservarEscenario();

    $this->actingAs($e['usuario'])
        ->get('/mi/cursos')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Mi/Cursos')
            ->has('cursos', 1)
            ->where('cursos.0.clave', $e['grupo']->clave)
            ->where('cursos.0.total', 1)
            ->where('cursos.0.completadas', 0)
            ->where('cursos.0.por_reservar', 1)
            // Las prácticas viven en el detalle, no aquí.
            ->missing('cursos.0.practicas')
        );
});

it('el detalle del curso lista sus prácticas', function () {
    $e = reservarEscenario();

    $this->actingAs($e['usuario'])
        ->get("/mi/cursos/{$e['grupo']->id_grupo}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Mi/Curso')
            ->where('curso.clave', $e['grupo']->clave)
            ->has('practicas', 1)
            ->where('practicas.0.estado', 'por reservar')
        );
});

it('bloquea el detalle de un curso en el que no está inscrito', function () {
    $e = reservarEscenario(estatusInscripcion: null);

    $this->actingAs($e['usuario'])
        ->get("/mi/cursos/{$e['grupo']->id_grupo}")
        ->assertForbidden();
});

it('marca reservada solo cuando la fecha no ha pasado', function () {
    $e = reservarEscenario();
    Reserva::create([
        'id_evento' => $e['evento']->id_evento,
        'id_alumno' => $e['alumno']->id_alumno,
        'inicio_slot' => $e['evento']->fecha_hora_inicio,
    ]);

    $this->actingAs($e['usuario'])
        ->get("/mi/cursos/{$e['grupo']->id_grupo}")
        ->assertInertia(fn ($p) => $p->where('practicas.0.estado', 'reservada')->etc());
});

it('una reserva sobre un evento terminado no queda como reservada, sino perdida', function () {
    $e = reservarEscenario([
        'fecha_hora_inicio' => now()->subDays(4),
        'fecha_hora_fin' => now()->subDays(4)->addHour(),
    ]);
    Reserva::create([
        'id_evento' => $e['evento']->id_evento,
        'id_alumno' => $e['alumno']->id_alumno,
        'inicio_slot' => $e['evento']->fecha_hora_inicio,
    ]);

    $this->actingAs($e['usuario'])
        ->get("/mi/cursos/{$e['grupo']->id_grupo}")
        ->assertInertia(fn ($p) => $p->where('practicas.0.estado', 'perdida')->etc());
});

it('una práctica con sesión completada muestra su calificación', function () {
    $e = reservarEscenario();
    SesionPractica::create([
        'id_practica' => $e['evento']->id_practica,
        'id_evento' => $e['evento']->id_evento,
        'id_alumno' => $e['alumno']->id_alumno,
        'fecha_inicio' => now()->subDay(),
        'estatus' => 'completada',
        'calificacion' => 88,
    ]);

    $this->actingAs($e['usuario'])
        ->get("/mi/cursos/{$e['grupo']->id_grupo}")
        ->assertInertia(fn ($p) => $p
            ->where('practicas.0.estado', 'completada')
            ->where('practicas.0.calificacion', 88)
            ->etc()
        );
});

it('marca sin agendar una práctica de la materia que nadie ha puesto en la agenda', function () {
    $e = reservarEscenario();
    Practica::create([
        'id_materia' => $e['grupo']->id_materia,
        'titulo' => 'P2 sin agendar',
        'orden' => 2,
        'escena_referencia' => 'x',
    ]);

    $this->actingAs($e['usuario'])
        ->get("/mi/cursos/{$e['grupo']->id_grupo}")
        ->assertInertia(fn ($p) => $p
            ->has('practicas', 2)
            ->where('practicas.1.estado', 'sin agendar')
            ->where('practicas.1.id_evento', null)
            ->etc()
        );
});

it('no deja ver cursos a quien no es alumno', function () {
    $e = reservarEscenario();
    $maestro = $e['grupo']->maestro->usuario;

    $this->actingAs($maestro)->get('/mi/cursos')->assertForbidden();
});
