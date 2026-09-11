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

// Reloj congelado a miércoles: con now()->addDay() un domingo real caería en la
// semana siguiente y los eventos desaparecerían del rango [lunes..domingo].
beforeEach(fn () => Carbon::setTestNow('2026-07-08 09:00:00'));

function calendarioGrupo(): Grupo
{
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $uM = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $uM->id_usuario, 'numero_empleado' => fake()->unique()->numerify('EMP####')]);
    $materia = Materia::create(['clave' => fake()->unique()->bothify('MAT-###'), 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::firstOrCreate(['nombre' => '2026-1'], ['fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-06-15', 'activo' => true]);

    return Grupo::create(['id_materia' => $materia->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
}

function calendarioEvento(Grupo $grupo, array $attrs = []): EventoAgenda
{
    $practica = Practica::create(['id_materia' => $grupo->id_materia, 'titulo' => 'P1', 'orden' => 1, 'escena_referencia' => 'Lab_1']);

    return EventoAgenda::create(array_merge([
        'id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now()->addDay(), 'fecha_hora_fin' => now()->addDay()->addHour(),
        'estatus' => 'programado', 'cupo_maximo' => 5,
    ], $attrs));
}

/**
 * @return array{0: Usuario, 1: Alumno}
 */
function calendarioAlumno(?Grupo $grupo = null, string $estatus = 'activa'): array
{
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $u = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'A', 'apellidos' => 'L', 'activo' => true]);
    $carrera = Carrera::firstOrCreate(['clave' => 'ISC'], ['nombre' => 'ISC', 'duracion_semestres' => 9]);
    $alumno = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => fake()->unique()->numerify('2025####'), 'semestre_actual' => 3, 'generacion' => '2025']);
    if ($grupo) {
        Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => $estatus]);
    }

    return [$u, $alumno];
}

it('muestra solo eventos de grupos con inscripción activa', function () {
    $grupoMio = calendarioGrupo();
    $grupoAjeno = calendarioGrupo();
    $grupoBaja = calendarioGrupo();
    calendarioEvento($grupoMio);
    calendarioEvento($grupoAjeno);
    calendarioEvento($grupoBaja);

    [$u, $alumno] = calendarioAlumno($grupoMio);
    Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupoBaja->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'baja']);

    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page->component('Mi/Calendario')->has('eventos', 1)
    );
});

it('expone mi_reserva y puede_cancelar cuando el alumno ya reservó', function () {
    $grupo = calendarioGrupo();
    $evento = calendarioEvento($grupo);
    [$u, $alumno] = calendarioAlumno($grupo);
    $reserva = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);

    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page->component('Mi/Calendario')
            ->where('eventos.0.mi_reserva.id_reserva', $reserva->id_reserva)
            ->where('eventos.0.puede_reservar', false)
            ->where('eventos.0.puede_cancelar', true)
            ->where('eventos.0.puede_jugar', false)
    );
});

it('puede_jugar solo en ventana con reserva activa, nunca en cancelados', function () {
    $grupo = calendarioGrupo();
    $enCurso = calendarioEvento($grupo, [
        'fecha_hora_inicio' => now()->subMinutes(5),
        'fecha_hora_fin' => now()->addHour(),
    ]);
    [$u, $alumno] = calendarioAlumno($grupo);
    Reserva::create(['id_evento' => $enCurso->id_evento, 'id_alumno' => $alumno->id_alumno]);

    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page->where('eventos.0.puede_jugar', true)
    );

    $enCurso->update(['estatus' => 'cancelado']);
    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page->where('eventos.0.puede_jugar', false)
    );
});

it('expone lleno y finalizado calculados en servidor', function () {
    $grupo = calendarioGrupo();
    $llenoEvento = calendarioEvento($grupo, ['cupo_maximo' => 1]);
    [$u, $alumno] = calendarioAlumno($grupo);
    [, $otro] = calendarioAlumno($grupo);
    Reserva::create(['id_evento' => $llenoEvento->id_evento, 'id_alumno' => $otro->id_alumno]);

    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page->where('eventos.0.lleno', true)->where('eventos.0.finalizado', false)
    );

    // Evento pasado 'programado' sin reserva: no está lleno, está finalizado.
    $llenoEvento->update([
        'cupo_maximo' => 5,
        'fecha_hora_inicio' => now()->subHours(3),
        'fecha_hora_fin' => now()->subHours(2),
    ]);
    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page->where('eventos.0.lleno', false)->where('eventos.0.finalizado', true)
    );
});

/*
 * El panel del alumno dejó de ser un historial de reservas —canceladas
 * incluidas— para volverse un resumen de estado: lo que tiene apartado, lo que
 * le falta apartar, y en qué grupos está. La intención del test es la misma;
 * cambió la forma.
 */
it('resume lo que el alumno tiene reservado y en qué grupo está', function () {
    $grupo = calendarioGrupo();
    $evento = calendarioEvento($grupo);
    [$u, $alumno] = calendarioAlumno($grupo);
    Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno]);

    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page->has('proximas', 1)
            ->where('proximas.0.id_evento', $evento->id_evento)
            ->where('proximas.0.cancelado', false)
            // Ya la reservó: no puede quedar como pendiente.
            ->has('pendientes', 0)
            ->has('grupos', 1)
            ->where('grupos.0.clave', $grupo->clave)
    );
});

it('lista como pendiente una práctica agendada que aún no reserva, una vez por práctica', function () {
    $grupo = calendarioGrupo();
    $primera = calendarioEvento($grupo);
    // Misma práctica, otra fecha: son alternativas, no dos pendientes.
    EventoAgenda::create([
        'id_practica' => $primera->id_practica,
        'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now()->addDays(5),
        'fecha_hora_fin' => now()->addDays(5)->addHour(),
        'estatus' => 'programado',
        'cupo_maximo' => 5,
    ]);
    [$u] = calendarioAlumno($grupo);

    $this->actingAs($u)->get('/mi/calendario')->assertInertia(
        fn (Assert $page) => $page->has('pendientes', 1)
            ->where('pendientes.0.fechas', 2)
            ->has('proximas', 0)
    );
});
