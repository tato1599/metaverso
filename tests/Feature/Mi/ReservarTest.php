<?php

use App\Models\Alumno;
use App\Models\EventoAgenda;
use App\Models\Maestro;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

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

/*
 * Una práctica se cursa UNA vez. Cuando el maestro la agenda varias veces, esas
 * fechas son alternativas y no sesiones acumulables: cada una ocupa un lugar de
 * un cupo escaso. El guard es por práctica, no por evento.
 */
function otraFechaDeLaMismaPractica(EventoAgenda $evento, array $attrs = []): EventoAgenda
{
    return EventoAgenda::create(array_merge([
        'id_practica' => $evento->id_practica,
        'id_grupo' => $evento->id_grupo,
        'fecha_hora_inicio' => now()->addDays(4),
        'fecha_hora_fin' => now()->addDays(4)->addHour(),
        'estatus' => 'programado',
        'cupo_maximo' => 5,
    ], $attrs));
}

it('rechaza reservar dos fechas de la misma práctica', function () {
    $e = reservarEscenario();
    $otro = otraFechaDeLaMismaPractica($e['evento']);

    $this->actingAs($e['usuario'])->post('/mi/reservas', ['id_evento' => $e['evento']->id_evento]);

    $this->from('/mi/calendario')
        ->post('/mi/reservas', ['id_evento' => $otro->id_evento])
        ->assertSessionHasErrors('evento');

    expect(Reserva::where('id_alumno', $e['alumno']->id_alumno)->where('estatus', 'activa')->count())->toBe(1);
});

it('permite reservar una reposición si la fecha anterior ya terminó', function () {
    $e = reservarEscenario([
        'fecha_hora_inicio' => now()->subDays(3),
        'fecha_hora_fin' => now()->subDays(3)->addHour(),
    ]);
    Reserva::create([
        'id_evento' => $e['evento']->id_evento,
        'id_alumno' => $e['alumno']->id_alumno,
        'inicio_slot' => $e['evento']->fecha_hora_inicio,
    ]);
    $reposicion = otraFechaDeLaMismaPractica($e['evento']);

    $this->actingAs($e['usuario'])
        ->post('/mi/reservas', ['id_evento' => $reposicion->id_evento])
        ->assertSessionHasNoErrors();

    expect($reposicion->reservasActivas()->count())->toBe(1);
});

it('cambia la reserva de fecha en una sola operación con cambiar_de', function () {
    $e = reservarEscenario();
    $otro = otraFechaDeLaMismaPractica($e['evento']);
    $this->actingAs($e['usuario'])->post('/mi/reservas', ['id_evento' => $e['evento']->id_evento]);
    $previa = Reserva::where('id_alumno', $e['alumno']->id_alumno)->where('estatus', 'activa')->firstOrFail();

    $this->post('/mi/reservas', ['id_evento' => $otro->id_evento, 'cambiar_de' => $previa->id_reserva])
        ->assertSessionHasNoErrors();

    expect($previa->fresh()->estatus)->toBe('cancelada')
        ->and($otro->reservasActivas()->count())->toBe(1)
        ->and($e['evento']->reservasActivas()->count())->toBe(0);
});

it('no deja al alumno sin reserva si el horario nuevo está lleno', function () {
    $e = reservarEscenario();
    $otro = otraFechaDeLaMismaPractica($e['evento'], ['cupo_maximo' => 1]);
    $this->actingAs($e['usuario'])->post('/mi/reservas', ['id_evento' => $e['evento']->id_evento]);
    $previa = Reserva::where('id_alumno', $e['alumno']->id_alumno)->where('estatus', 'activa')->firstOrFail();

    // Otro alumno toma el único lugar de la fecha nueva.
    [$otroUsuario, $otroAlumno] = reservarNuevoAlumno($e['grupo']);
    Reserva::create([
        'id_evento' => $otro->id_evento,
        'id_alumno' => $otroAlumno->id_alumno,
        'inicio_slot' => $otro->fecha_hora_inicio,
    ]);

    $this->actingAs($e['usuario'])
        ->from('/mi/calendario')
        ->post('/mi/reservas', ['id_evento' => $otro->id_evento, 'cambiar_de' => $previa->id_reserva])
        ->assertSessionHasErrors('evento');

    // La transacción se revierte entera: conserva su lugar original.
    expect($previa->fresh()->estatus)->toBe('activa');
});
