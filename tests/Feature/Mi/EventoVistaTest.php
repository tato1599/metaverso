<?php

use App\Models\EventoAgenda;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * La vista de una práctica es UNA pantalla que cambia según el estado del
 * alumno. Lo que se prueba aquí es que ese estado lo decide el backend —la UI
 * solo lo pinta— y que la autorización es la misma que la de reservar.
 */

it('muestra la práctica al alumno inscrito, con sus horarios', function () {
    $e = reservarEscenario();

    $this->actingAs($e['usuario'])
        ->get("/mi/eventos/{$e['evento']->id_evento}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Mi/Evento')
            ->where('evento.id_evento', $e['evento']->id_evento)
            ->where('evento.puede_reservar', true)
            ->where('evento.mi_reserva', null)
            ->where('evento.puede_jugar', false)
            ->has('evento.slots')
        );
});

it('bloquea a quien no está inscrito en el grupo', function () {
    $e = reservarEscenario(estatusInscripcion: null);

    $this->actingAs($e['usuario'])
        ->get("/mi/eventos/{$e['evento']->id_evento}")
        ->assertForbidden();
});

it('bloquea a un alumno con inscripción no activa', function () {
    $e = reservarEscenario(estatusInscripcion: 'baja');

    $this->actingAs($e['usuario'])
        ->get("/mi/eventos/{$e['evento']->id_evento}")
        ->assertForbidden();
});

it('cambia a estado reservado cuando el alumno ya tiene lugar', function () {
    $e = reservarEscenario();
    Reserva::create([
        'id_evento' => $e['evento']->id_evento,
        'id_alumno' => $e['alumno']->id_alumno,
        'inicio_slot' => $e['evento']->fecha_hora_inicio,
    ]);

    $this->actingAs($e['usuario'])
        ->get("/mi/eventos/{$e['evento']->id_evento}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Mi/Evento')
            ->where('evento.puede_reservar', false)
            ->where('evento.puede_cancelar', true)
            ->where('evento.puede_jugar', false)
            ->has('evento.mi_reserva.id_reserva')
        );
});

it('habilita jugar solo dentro de la ventana del slot reservado', function () {
    $e = reservarEscenario([
        'fecha_hora_inicio' => now()->subMinutes(10),
        'fecha_hora_fin' => now()->addMinutes(50),
    ]);
    Reserva::create([
        'id_evento' => $e['evento']->id_evento,
        'id_alumno' => $e['alumno']->id_alumno,
        'inicio_slot' => $e['evento']->fecha_hora_inicio,
    ]);

    $this->actingAs($e['usuario'])
        ->get("/mi/eventos/{$e['evento']->id_evento}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('evento.puede_jugar', true));
});

it('lista otras fechas de la misma práctica y grupo, sin incluirse a sí misma', function () {
    $e = reservarEscenario();
    $otro = EventoAgenda::create([
        'id_practica' => $e['evento']->id_practica,
        'id_grupo' => $e['evento']->id_grupo,
        'fecha_hora_inicio' => now()->addDays(3),
        'fecha_hora_fin' => now()->addDays(3)->addHour(),
        'estatus' => 'programado',
        'cupo_maximo' => 5,
    ]);

    $this->actingAs($e['usuario'])
        ->get("/mi/eventos/{$e['evento']->id_evento}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->has('otros', 1)
            ->where('otros.0.id_evento', $otro->id_evento)
        );
});

it('no ofrece otras fechas que ya terminaron', function () {
    $e = reservarEscenario();
    EventoAgenda::create([
        'id_practica' => $e['evento']->id_practica,
        'id_grupo' => $e['evento']->id_grupo,
        'fecha_hora_inicio' => now()->subDays(3),
        'fecha_hora_fin' => now()->subDays(3)->addHour(),
        'estatus' => 'programado',
        'cupo_maximo' => 5,
    ]);

    $this->actingAs($e['usuario'])
        ->get("/mi/eventos/{$e['evento']->id_evento}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('otros', 0));
});

it('marca finalizado un evento pasado aunque el alumno tenga reserva', function () {
    $e = reservarEscenario([
        'fecha_hora_inicio' => now()->subDays(2),
        'fecha_hora_fin' => now()->subDays(2)->addHour(),
    ]);
    Reserva::create([
        'id_evento' => $e['evento']->id_evento,
        'id_alumno' => $e['alumno']->id_alumno,
        'inicio_slot' => $e['evento']->fecha_hora_inicio,
    ]);

    $this->actingAs($e['usuario'])
        ->get("/mi/eventos/{$e['evento']->id_evento}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('evento.finalizado', true)
            ->where('evento.puede_jugar', false)
            ->where('evento.puede_cancelar', false)
            ->has('evento.mi_reserva')
        );
});

/*
 * El calendario acota su navegación a las semanas que de verdad tienen algo, más
 * la actual: un calendario que avanza al infinito por semanas vacías no informa.
 */
it('acota la navegación del calendario a las semanas con eventos y la actual', function () {
    $e = reservarEscenario(['fecha_hora_inicio' => now()->addWeeks(3), 'fecha_hora_fin' => now()->addWeeks(3)->addHour()]);

    $this->actingAs($e['usuario'])
        ->get('/mi/calendario')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('limites.min', now()->startOfWeek()->toDateString())
            ->where('limites.max', now()->addWeeks(3)->startOfWeek()->toDateString())
            ->etc()
        );
});

it('incluye la semana actual en los límites aunque no haya eventos', function () {
    $e = reservarEscenario();
    $e['evento']->delete();

    $this->actingAs($e['usuario'])
        ->get('/mi/calendario')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('limites.min', now()->startOfWeek()->toDateString())
            ->where('limites.max', now()->startOfWeek()->toDateString())
            ->etc()
        );
});
