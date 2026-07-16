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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// Reloj congelado a miércoles (como CalendarioTest): eventos de hoy y de mañana
// caen siempre dentro de la semana [lunes..domingo] del calendario.
beforeEach(fn () => Carbon::setTestNow('2026-07-08 09:00:00'));

function slotsUsuario(string $rol): Usuario
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

/**
 * @return array{maestro: Usuario, grupo: Grupo}
 */
function slotsGrupo(): array
{
    $uM = slotsUsuario('Maestro');
    $maestro = Maestro::create(['id_usuario' => $uM->id_usuario, 'numero_empleado' => fake()->unique()->numerify('EMP####')]);
    $materia = Materia::create(['clave' => fake()->unique()->bothify('MAT-###'), 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::firstOrCreate(['nombre' => '2026-1'], ['fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-06-15', 'activo' => true]);
    $grupo = Grupo::create(['id_materia' => $materia->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);

    return ['maestro' => $uM->fresh(), 'grupo' => $grupo];
}

/**
 * Evento multi-slot por default: mañana 10:00-14:00 con práctica de 60 min (4 slots).
 */
function slotsEvento(Grupo $grupo, ?int $duracion = 60, array $attrs = []): EventoAgenda
{
    $practica = Practica::create([
        'id_materia' => $grupo->id_materia,
        'titulo' => 'P'.fake()->unique()->numberBetween(1, 9999),
        'orden' => 1,
        'escena_referencia' => 'Lab_1',
        'duracion_estimada' => $duracion,
    ]);

    return EventoAgenda::create(array_merge([
        'id_practica' => $practica->id_practica,
        'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now()->addDay()->setTime(10, 0),
        'fecha_hora_fin' => now()->addDay()->setTime(14, 0),
        'estatus' => 'programado',
        'cupo_maximo' => 5,
    ], $attrs));
}

/**
 * @return array{0: Usuario, 1: Alumno}
 */
function slotsAlumno(Grupo $grupo): array
{
    $u = slotsUsuario('Alumno');
    $carrera = Carrera::firstOrCreate(['clave' => 'ISC'], ['nombre' => 'ISC', 'duracion_semestres' => 9]);
    $alumno = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => fake()->unique()->numerify('2025####'), 'semestre_actual' => 3, 'generacion' => '2025']);
    Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);

    return [$u, $alumno];
}

function slotsClave(EventoAgenda $evento, int $indice): string
{
    return $evento->slots()[$indice]['inicio']->format('Y-m-d\TH:i');
}

// ---------------------------------------------------------------------------
// Partición (EventoAgenda::slots / duracionSlotMinutos)
// ---------------------------------------------------------------------------

it('particiona una ventana de 4h en 4 slots de 60 min', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo']);

    $slots = $evento->slots();
    expect($slots)->toHaveCount(4)
        ->and($slots[0]['inicio']->format('H:i'))->toBe('10:00')
        ->and($slots[0]['fin']->format('H:i'))->toBe('11:00')
        ->and($slots[3]['inicio']->format('H:i'))->toBe('13:00')
        ->and($slots[3]['fin']->format('H:i'))->toBe('14:00');
});

it('en ventana no divisible solo caben slots completos', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo'], 60, ['fecha_hora_fin' => now()->addDay()->setTime(14, 30)]);

    $slots = $evento->slots();
    expect($slots)->toHaveCount(4)
        ->and(end($slots)['fin']->format('H:i'))->toBe('14:00');
});

it('sin duración estimada la ventana completa es el único slot', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo'], null);

    expect($evento->duracionSlotMinutos())->toBeNull()
        ->and($evento->slots())->toHaveCount(1)
        ->and($evento->slots()[0]['inicio']->eq($evento->fecha_hora_inicio))->toBeTrue()
        ->and($evento->slots()[0]['fin']->eq($evento->fecha_hora_fin))->toBeTrue();
});

it('ventana menor que la duración: un solo slot con la ventana entera', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo'], 60, ['fecha_hora_fin' => now()->addDay()->setTime(10, 45)]);

    $slots = $evento->slots();
    expect($slots)->toHaveCount(1)
        ->and($slots[0]['fin']->format('H:i'))->toBe('10:45');
});

it('duración no positiva se trata como sin duración (enmienda F6)', function (int $duracion) {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo'], $duracion);

    expect($evento->duracionSlotMinutos())->toBeNull()
        ->and($evento->slots())->toHaveCount(1);
})->with(['cero' => 0, 'negativa' => -30]);

// ---------------------------------------------------------------------------
// Reservar por slot (POST /mi/reservas)
// ---------------------------------------------------------------------------

it('reserva un horario y el cupo aplica por slot: slot lleno no bloquea otro', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo'], 60, ['cupo_maximo' => 1]);
    [$u1] = slotsAlumno($g['grupo']);
    [$u2, $a2] = slotsAlumno($g['grupo']);

    $this->actingAs($u1)->from('/mi/calendario')
        ->post('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => slotsClave($evento, 0)])
        ->assertRedirect('/mi/calendario');

    // Slot A lleno: mismo horario da 422, otro horario pasa.
    $this->actingAs($u2)
        ->postJson('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => slotsClave($evento, 0)])
        ->assertStatus(422)->assertJsonValidationErrors('evento');
    $this->actingAs($u2)->from('/mi/calendario')
        ->post('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => slotsClave($evento, 1)])
        ->assertRedirect('/mi/calendario');

    expect($evento->reservasActivas()->count())->toBe(2)
        ->and(Reserva::where('id_alumno', $a2->id_alumno)->first()->inicio_slot->eq($evento->slots()[1]['inicio']))->toBeTrue();
});

it('rechaza inicio_slot fuera de la partición o con formato inválido', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo']);
    [$u] = slotsAlumno($g['grupo']);
    $dia = $evento->fecha_hora_inicio->format('Y-m-d');

    // No alineado a la partición (los slots van en punto).
    $this->actingAs($u)->postJson('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => "{$dia}T10:30"])
        ->assertStatus(422)->assertJsonValidationErrors('inicio_slot');
    // Fuera de la ventana.
    $this->actingAs($u)->postJson('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => "{$dia}T15:00"])
        ->assertStatus(422)->assertJsonValidationErrors('inicio_slot');
    // Formato inválido (date_format Y-m-d\TH:i).
    $this->actingAs($u)->postJson('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => '10:00'])
        ->assertStatus(422)->assertJsonValidationErrors('inicio_slot');

    expect($evento->reservasActivas()->count())->toBe(0);
});

// El 422 por inicio_slot faltante aplica SOLO a eventos multi-slot (enmienda F1):
// en eventos de un solo slot el horario es implícito y el campo es opcional.
it('multi-slot sin inicio_slot da 422; un solo slot lo asume implícito', function () {
    $g = slotsGrupo();
    $multi = slotsEvento($g['grupo']);
    $single = slotsEvento($g['grupo'], null, ['fecha_hora_inicio' => now()->addDay()->setTime(16, 0), 'fecha_hora_fin' => now()->addDay()->setTime(17, 0)]);
    [$u, $alumno] = slotsAlumno($g['grupo']);

    $this->actingAs($u)->postJson('/mi/reservas', ['id_evento' => $multi->id_evento])
        ->assertStatus(422)->assertJsonValidationErrors('inicio_slot');

    $this->actingAs($u)->from('/mi/calendario')
        ->post('/mi/reservas', ['id_evento' => $single->id_evento])
        ->assertRedirect('/mi/calendario');
    expect(Reserva::where('id_alumno', $alumno->id_alumno)->first()->inicio_slot->eq($single->fecha_hora_inicio))->toBeTrue();
});

it('una sola reserva activa por evento aunque haya slots libres', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo']);
    [$u] = slotsAlumno($g['grupo']);

    $this->actingAs($u)->post('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => slotsClave($evento, 0)])
        ->assertRedirect();
    $this->actingAs($u)->postJson('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => slotsClave($evento, 1)])
        ->assertStatus(422)->assertJsonValidationErrors('evento');

    expect($evento->reservasActivas()->count())->toBe(1);
});

it('ventana ya iniciada: reserva y cancela horarios futuros; horario pasado da 422 (F2)', function () {
    $g = slotsGrupo();
    // Hoy 07:00-15:00, ahora son las 09:00: la ventana ya inició.
    $evento = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->setTime(7, 0),
        'fecha_hora_fin' => now()->setTime(15, 0),
    ]);
    [$u, $alumno] = slotsAlumno($g['grupo']);
    $dia = now()->format('Y-m-d');

    // Slot de la tarde (futuro): reservar OK.
    $this->actingAs($u)->post('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => "{$dia}T13:00"])
        ->assertRedirect();
    $reserva = Reserva::where('id_alumno', $alumno->id_alumno)->firstOrFail();

    // Cancelar mientras el slot no inicia: OK aunque el evento esté en curso.
    $this->actingAs($u)->delete("/mi/reservas/{$reserva->id_reserva}")->assertRedirect();
    expect($reserva->fresh()->estatus)->toBe('cancelada');

    // Slot ya pasado: 422.
    $this->actingAs($u)->postJson('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => "{$dia}T07:00"])
        ->assertStatus(422)->assertJsonValidationErrors('evento');
});

it('no permite cancelar cuando el horario reservado ya pasó', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->setTime(7, 0),
        'fecha_hora_fin' => now()->setTime(15, 0),
    ]);
    [$u, $alumno] = slotsAlumno($g['grupo']);
    $reserva = Reserva::create([
        'id_evento' => $evento->id_evento,
        'id_alumno' => $alumno->id_alumno,
        'inicio_slot' => now()->setTime(8, 0),
    ]);

    $this->actingAs($u)->deleteJson("/mi/reservas/{$reserva->id_reserva}")->assertStatus(422);
    expect($reserva->fresh()->estatus)->toBe('activa');
});

it('con segundos en la ventana, el string del calendario cae en la clave del servidor (F3)', function () {
    $g = slotsGrupo();
    // Evento creado "con now()": el inicio trae segundos != 0.
    $evento = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->addDay()->setTime(10, 0, 30),
        'fecha_hora_fin' => now()->addDay()->setTime(14, 0, 30),
        'cupo_maximo' => 1,
    ]);
    [$u1, $a1] = slotsAlumno($g['grupo']);
    [$u2] = slotsAlumno($g['grupo']);
    $slot2 = slotsClave($evento, 1); // '...T11:00' — el minuto del calendario, sin segundos

    $this->actingAs($u1)->post('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => $slot2])
        ->assertRedirect();

    // Se persistió el Carbon del servidor (con segundos), no el parseo del cliente.
    $reserva = Reserva::where('id_alumno', $a1->id_alumno)->firstOrFail();
    expect($reserva->inicio_slot->eq($evento->slots()[1]['inicio']))->toBeTrue()
        ->and($reserva->inicio_slot->format('s'))->toBe('30');

    // El conteo de cupo ve la reserva en la MISMA clave: cupo 1 => lleno.
    $this->actingAs($u2)->postJson('/mi/reservas', ['id_evento' => $evento->id_evento, 'inicio_slot' => $slot2])
        ->assertStatus(422)->assertJsonValidationErrors('evento');
});

// ---------------------------------------------------------------------------
// Jugar por slot (POST /mi/eventos/{evento}/jugar)
// ---------------------------------------------------------------------------

it('juega dentro del slot reservado; fuera del slot (pero dentro del evento) da 422', function () {
    $g = slotsGrupo();
    // Ventana 07:30-15:30; ahora 09:00 cae en el slot 08:30-09:30.
    $enCurso = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->setTime(7, 30),
        'fecha_hora_fin' => now()->setTime(15, 30),
    ]);
    [$u, $alumno] = slotsAlumno($g['grupo']);
    Reserva::create(['id_evento' => $enCurso->id_evento, 'id_alumno' => $alumno->id_alumno, 'inicio_slot' => now()->setTime(8, 30)]);

    $response = $this->actingAs($u)->post("/mi/eventos/{$enCurso->id_evento}/jugar");
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/jugar/');

    // Mismo alumno, otro evento en curso, pero su horario reservado es en la tarde.
    $otro = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->setTime(7, 0),
        'fecha_hora_fin' => now()->setTime(15, 0),
    ]);
    Reserva::create(['id_evento' => $otro->id_evento, 'id_alumno' => $alumno->id_alumno, 'inicio_slot' => now()->setTime(13, 0)]);

    $this->actingAs($u)->postJson("/mi/eventos/{$otro->id_evento}/jugar")->assertStatus(422);
});

it('reserva heredada del backfill (sin slot explícito) juega solo en el primer slot', function () {
    $g = slotsGrupo();
    // Primer slot 08:30-09:30 contiene las 09:00 actuales.
    $eventoA = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->setTime(8, 30),
        'fecha_hora_fin' => now()->setTime(12, 30),
    ]);
    [$u, $alumno] = slotsAlumno($g['grupo']);
    $heredada = Reserva::create(['id_evento' => $eventoA->id_evento, 'id_alumno' => $alumno->id_alumno]);

    // Hook creating (F1): cae al inicio de la ventana, como el backfill.
    expect($heredada->inicio_slot->eq($eventoA->fecha_hora_inicio))->toBeTrue();
    $this->actingAs($u)->post("/mi/eventos/{$eventoA->id_evento}/jugar")->assertRedirect();

    // Primer slot 07:00-08:00 ya pasó: dentro del evento pero fuera de su slot.
    $eventoB = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->setTime(7, 0),
        'fecha_hora_fin' => now()->setTime(11, 0),
    ]);
    Reserva::create(['id_evento' => $eventoB->id_evento, 'id_alumno' => $alumno->id_alumno]);

    $this->actingAs($u)->postJson("/mi/eventos/{$eventoB->id_evento}/jugar")->assertStatus(422);
});

it('la ventana de juego del slot se capa al fin del evento', function () {
    $g = slotsGrupo();
    // Ventana 08:00-08:45 con duración 60: único slot = la ventana. Sin la capa,
    // inicio+60 = 09:00 y las 09:00 actuales (inclusive) dejarían jugar.
    $evento = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->setTime(8, 0),
        'fecha_hora_fin' => now()->setTime(8, 45),
    ]);
    [$u, $alumno] = slotsAlumno($g['grupo']);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);

    $this->actingAs($u)->postJson("/mi/eventos/{$evento->id_evento}/jugar")->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Mi/Calendario: contrato de props por slot
// ---------------------------------------------------------------------------

it('calendario expone multi_slot, slots con ocupados/es_mio y mi_reserva con horario', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo']);
    [$u, $alumno] = slotsAlumno($g['grupo']);
    [, $otro] = slotsAlumno($g['grupo']);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $otro->id_alumno, 'inicio_slot' => $evento->slots()[0]['inicio']]);
    $mia = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno, 'inicio_slot' => $evento->slots()[1]['inicio']]);

    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page->component('Mi/Calendario')
            ->where('eventos.0.multi_slot', true)
            ->has('eventos.0.slots', 4)
            ->where('eventos.0.slots.0.inicio_local', slotsClave($evento, 0))
            ->where('eventos.0.slots.0.fin_local', $evento->slots()[0]['fin']->format('Y-m-d\TH:i'))
            ->where('eventos.0.slots.0.ocupados', 1)
            ->where('eventos.0.slots.0.es_mio', false)
            ->where('eventos.0.slots.1.ocupados', 1)
            ->where('eventos.0.slots.1.es_mio', true)
            ->where('eventos.0.slots.2.ocupados', 0)
            ->where('eventos.0.mi_reserva.id_reserva', $mia->id_reserva)
            ->where('eventos.0.mi_reserva.inicio_slot_local', slotsClave($evento, 1))
            ->where('eventos.0.puede_reservar', false)
            ->where('eventos.0.puede_cancelar', true)
            ->where('eventos.0.puede_jugar', false)
    );
});

it('puede_reservar es el OR de los slots y lleno exige todos los slots llenos', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo'], 60, [
        'fecha_hora_fin' => now()->addDay()->setTime(12, 0),
        'cupo_maximo' => 1,
    ]);
    [$u] = slotsAlumno($g['grupo']);
    [, $otro1] = slotsAlumno($g['grupo']);
    [, $otro2] = slotsAlumno($g['grupo']);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $otro1->id_alumno, 'inicio_slot' => $evento->slots()[0]['inicio']]);

    // Slot 1 lleno, slot 2 libre: el evento aún acepta reservas.
    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page
            ->where('eventos.0.slots.0.lleno', true)
            ->where('eventos.0.slots.0.puede_reservar', false)
            ->where('eventos.0.slots.1.puede_reservar', true)
            ->where('eventos.0.puede_reservar', true)
            ->where('eventos.0.lleno', false)
    );

    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $otro2->id_alumno, 'inicio_slot' => $evento->slots()[1]['inicio']]);

    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page
            ->where('eventos.0.puede_reservar', false)
            ->where('eventos.0.lleno', true)
            ->where('eventos.0.finalizado', false)
    );
});

it('lleno ignora slots pasados: ventana iniciada con futuros llenos marca lleno (F6 must-fix)', function () {
    $g = slotsGrupo();
    // Hoy 07:30-10:30, ahora 09:00: 07:30 y 08:30 ya iniciaron; solo 09:30 es futuro.
    $evento = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->setTime(7, 30),
        'fecha_hora_fin' => now()->setTime(10, 30),
        'cupo_maximo' => 1,
    ]);
    [$u] = slotsAlumno($g['grupo']);
    [, $otro] = slotsAlumno($g['grupo']);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $otro->id_alumno, 'inicio_slot' => $evento->slots()[2]['inicio']]);

    // Los slots pasados están libres, pero el único horario futuro está lleno.
    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page
            ->where('eventos.0.slots.0.lleno', false)
            ->where('eventos.0.puede_reservar', false)
            ->where('eventos.0.lleno', true)
    );
});

it('lleno excluye al alumno con reserva propia (F6 must-fix)', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo'], 60, [
        'fecha_hora_fin' => now()->addDay()->setTime(12, 0),
        'cupo_maximo' => 1,
    ]);
    [$u, $alumno] = slotsAlumno($g['grupo']);
    [, $otro] = slotsAlumno($g['grupo']);
    [$uSinReserva] = slotsAlumno($g['grupo']);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno, 'inicio_slot' => $evento->slots()[0]['inicio']]);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $otro->id_alumno, 'inicio_slot' => $evento->slots()[1]['inicio']]);

    // Ambos horarios llenos: para quien ya tiene lugar el evento no está "lleno".
    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page
            ->where('eventos.0.mi_reserva.inicio_slot_local', slotsClave($evento, 0))
            ->where('eventos.0.lleno', false)
    );
    $this->actingAs($uSinReserva)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page->where('eventos.0.lleno', true)
    );
});

it('puede_jugar y puede_cancelar se calculan contra el slot reservado', function () {
    $g = slotsGrupo();
    // eventos.0 = ventana 07:00-11:00, mi horario 10:00 (futuro): aún no juega, sí cancela.
    $eventoB = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->setTime(7, 0),
        'fecha_hora_fin' => now()->setTime(11, 0),
    ]);
    // eventos.1 = ventana 08:30-12:30, mi horario 08:30 (en curso a las 09:00): juega.
    $eventoA = slotsEvento($g['grupo'], 60, [
        'fecha_hora_inicio' => now()->setTime(8, 30),
        'fecha_hora_fin' => now()->setTime(12, 30),
    ]);
    [$u, $alumno] = slotsAlumno($g['grupo']);
    Reserva::create(['id_evento' => $eventoB->id_evento, 'id_alumno' => $alumno->id_alumno, 'inicio_slot' => now()->setTime(10, 0)]);
    Reserva::create(['id_evento' => $eventoA->id_evento, 'id_alumno' => $alumno->id_alumno, 'inicio_slot' => now()->setTime(8, 30)]);

    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page
            ->where('eventos.0.puede_jugar', false)
            ->where('eventos.0.puede_cancelar', true)
            ->where('eventos.1.puede_jugar', true)
            ->where('eventos.1.puede_cancelar', false)
    );
});

// ---------------------------------------------------------------------------
// Panel: ocupación por horario, chip multi_slot y reprogramación (F4)
// ---------------------------------------------------------------------------

it('panel detalle expone ocupacion por horario e inicio_slot_local en reservas', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo']);
    [, $alumno] = slotsAlumno($g['grupo']);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno, 'inicio_slot' => $evento->slots()[1]['inicio']]);

    $this->actingAs($g['maestro'])->get("/panel/eventos/{$evento->id_evento}")->assertInertia(
        fn (Assert $page) => $page->component('Panel/EventoDetalle')
            ->where('evento.multi_slot', true)
            ->has('evento.ocupacion', 4)
            ->where('evento.ocupacion.0.inicio_local', slotsClave($evento, 0))
            ->where('evento.ocupacion.0.ocupados', 0)
            ->where('evento.ocupacion.1.ocupados', 1)
            ->where('reservas.0.inicio_slot_local', slotsClave($evento, 1))
    );
});

it('panel agenda marca multi_slot en el chip del evento', function () {
    $g = slotsGrupo();
    slotsEvento($g['grupo']); // 10:00-14:00, 4 slots
    slotsEvento($g['grupo'], null, [
        'fecha_hora_inicio' => now()->addDay()->setTime(16, 0),
        'fecha_hora_fin' => now()->addDay()->setTime(17, 0),
    ]);

    $this->actingAs($g['maestro'])->get('/panel/agenda')->assertInertia(
        fn (Assert $page) => $page->component('Panel/Agenda')
            ->where('eventos.0.multi_slot', true)
            ->where('eventos.1.multi_slot', false)
    );
});

it('reprogramar un evento de un solo slot re-mapea las reservas activas (F4)', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo'], null, ['fecha_hora_fin' => now()->addDay()->setTime(11, 0)]);
    [, $alumno] = slotsAlumno($g['grupo']);
    $reserva = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);

    $this->actingAs($g['maestro'])->put("/panel/eventos/{$evento->id_evento}", [
        'fecha_hora_inicio' => now()->addDay()->setTime(12, 0)->toDateTimeString(),
        'fecha_hora_fin' => now()->addDay()->setTime(13, 0)->toDateTimeString(),
        'cupo_maximo' => 5,
    ])->assertRedirect();

    // Sin claves inicio_slot huérfanas: la reserva sigue a la nueva ventana.
    expect($reserva->fresh()->inicio_slot->eq($evento->fresh()->fecha_hora_inicio))->toBeTrue()
        ->and($reserva->fresh()->inicio_slot->format('H:i'))->toBe('12:00');
});

it('reprogramar multi-slot con reservas activas da 422; sin reservas o sin mover fechas pasa (F4)', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo']);
    [, $alumno] = slotsAlumno($g['grupo']);
    $reserva = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno, 'inicio_slot' => $evento->slots()[2]['inicio']]);
    $mismasFechas = [
        'fecha_hora_inicio' => $evento->fecha_hora_inicio->toDateTimeString(),
        'fecha_hora_fin' => $evento->fecha_hora_fin->toDateTimeString(),
    ];

    // Cambiar fechas con reservas activas: 422.
    $this->actingAs($g['maestro'])->putJson("/panel/eventos/{$evento->id_evento}", [
        'fecha_hora_inicio' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
        'fecha_hora_fin' => now()->addDays(2)->setTime(14, 0)->toDateTimeString(),
        'cupo_maximo' => 5,
    ])->assertStatus(422)->assertJsonValidationErrors('fecha_hora_inicio');
    expect($evento->fresh()->fecha_hora_inicio->format('H:i'))->toBe('10:00');

    // Sin mover fechas (solo cupo): pasa.
    $this->actingAs($g['maestro'])->put("/panel/eventos/{$evento->id_evento}", [...$mismasFechas, 'cupo_maximo' => 8])
        ->assertRedirect();

    // Sin reservas activas: reprogramar pasa.
    $reserva->update(['estatus' => 'cancelada']);
    $this->actingAs($g['maestro'])->put("/panel/eventos/{$evento->id_evento}", [
        'fecha_hora_inicio' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
        'fecha_hora_fin' => now()->addDays(2)->setTime(14, 0)->toDateTimeString(),
        'cupo_maximo' => 8,
    ])->assertRedirect();
});

it('la reducción de cupo se valida contra el horario más ocupado, no el total (F4)', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo']);
    [, $a1] = slotsAlumno($g['grupo']);
    [, $a2] = slotsAlumno($g['grupo']);
    [, $a3] = slotsAlumno($g['grupo']);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $a1->id_alumno, 'inicio_slot' => $evento->slots()[0]['inicio']]);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $a2->id_alumno, 'inicio_slot' => $evento->slots()[1]['inicio']]);
    $fechas = [
        'fecha_hora_inicio' => $evento->fecha_hora_inicio->toDateTimeString(),
        'fecha_hora_fin' => $evento->fecha_hora_fin->toDateTimeString(),
    ];

    // 2 reservas totales pero 1 por horario: cupo 1 es válido.
    $this->actingAs($g['maestro'])->put("/panel/eventos/{$evento->id_evento}", [...$fechas, 'cupo_maximo' => 1])
        ->assertRedirect();

    // Con 2 en el mismo horario, cupo 1 ya no alcanza.
    $evento->fresh()->update(['cupo_maximo' => 5]);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $a3->id_alumno, 'inicio_slot' => $evento->slots()[0]['inicio']]);
    $this->actingAs($g['maestro'])->putJson("/panel/eventos/{$evento->id_evento}", [...$fechas, 'cupo_maximo' => 1])
        ->assertStatus(422)->assertJsonValidationErrors('cupo_maximo');
});

// ---------------------------------------------------------------------------
// Admin/Prácticas: cambiar duración con reservas vigentes (F5)
// ---------------------------------------------------------------------------

it('bloquea cambiar duracion_estimada con reservas activas en eventos vigentes (F5)', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo']);
    $practica = $evento->practica;
    [, $alumno] = slotsAlumno($g['grupo']);
    $reserva = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno, 'inicio_slot' => $evento->slots()[1]['inicio']]);
    $admin = slotsUsuario('Admin');
    $payload = fn (?int $duracion) => [
        'id_materia' => $practica->id_materia,
        'titulo' => $practica->titulo,
        'orden' => 1,
        'escena_referencia' => 'recolecta',
        'duracion_estimada' => $duracion,
    ];

    // Cambiarla dejaría inicio_slot fuera de la nueva partición: 422 con conteo.
    $this->actingAs($admin)->putJson("/admin/practicas/{$practica->id_practica}", $payload(90))
        ->assertStatus(422)->assertJsonValidationErrors('duracion_estimada');
    expect($practica->fresh()->duracion_estimada)->toBe(60);

    // Sin cambiar la duración, el update pasa aunque haya reservas.
    $this->actingAs($admin)->put("/admin/practicas/{$practica->id_practica}", $payload(60))
        ->assertRedirect();

    // Sin reservas activas vigentes, cambiarla pasa.
    $reserva->update(['estatus' => 'cancelada']);
    $this->actingAs($admin)->put("/admin/practicas/{$practica->id_practica}", $payload(90))
        ->assertRedirect();
    expect($practica->fresh()->duracion_estimada)->toBe(90);
});

it('omitir duracion_estimada en el update no cuenta como cambio a null (F6 must-fix)', function () {
    $g = slotsGrupo();
    $evento = slotsEvento($g['grupo']);
    $practica = $evento->practica;
    [, $alumno] = slotsAlumno($g['grupo']);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno, 'inicio_slot' => $evento->slots()[1]['inicio']]);
    $admin = slotsUsuario('Admin');

    // La llave ausente no es "cambiar a null": el update pasa y la duración queda intacta.
    $this->actingAs($admin)->putJson("/admin/practicas/{$practica->id_practica}", [
        'id_materia' => $practica->id_materia,
        'titulo' => $practica->titulo,
        'orden' => 1,
        'escena_referencia' => 'recolecta',
    ])->assertRedirect();
    expect($practica->fresh()->duracion_estimada)->toBe(60);
});
