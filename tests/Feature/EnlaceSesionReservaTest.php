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
use App\Models\SesionPractica;
use App\Models\Usuario;
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * @return array{usuario: Usuario, alumno: Alumno, evento: EventoAgenda, tokenPlano: string}
 */
function enlaceEscenario(): array
{
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $uM = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $uM->id_usuario, 'numero_empleado' => fake()->unique()->numerify('EMP####')]);
    $materia = Materia::create(['clave' => fake()->unique()->bothify('MAT-###'), 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::firstOrCreate(['nombre' => '2026-1'], ['fecha_inicio' => '2026-01-15', 'fecha_fin' => '2026-06-15', 'activo' => true]);
    $grupo = Grupo::create(['id_materia' => $materia->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $practica = Practica::create(['id_materia' => $materia->id_materia, 'titulo' => 'P1', 'orden' => 1, 'escena_referencia' => 'Lab_1']);
    $evento = EventoAgenda::create([
        'id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now()->subMinutes(5), 'fecha_hora_fin' => now()->addHour(),
        'estatus' => 'programado', 'cupo_maximo' => 5,
    ]);

    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $uA = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'A', 'apellidos' => 'L']);
    $carrera = Carrera::firstOrCreate(['clave' => 'ISC'], ['nombre' => 'ISC', 'duracion_semestres' => 9]);
    $alumno = Alumno::create(['id_usuario' => $uA->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => fake()->unique()->numerify('2025####'), 'semestre_actual' => 3, 'generacion' => '2025']);
    Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);

    $res = app(MagicLinkService::class)->generar($uA->id_usuario, $evento->id_evento);

    return ['usuario' => $uA, 'alumno' => $alumno, 'evento' => $evento, 'tokenPlano' => $res['token']];
}

function enlaceBearer(string $tokenPlano): string
{
    return test()->postJson('/api/game/redeem', ['token' => $tokenPlano])->json('access_token');
}

it('el redeem liga el evento al bearer vía ability y conserva el contrato exacto', function () {
    $e = enlaceEscenario();
    $response = $this->postJson('/api/game/redeem', ['token' => $e['tokenPlano']])->assertOk();

    expect(array_keys($response->json()))->toBe(['access_token', 'token_type', 'alumno', 'practica', 'evento']);

    $pat = $e['usuario']->tokens()->latest('id')->first();
    expect($pat->abilities)->toBe(['game', 'evento:'.$e['evento']->id_evento]);
});

it('start enlaza la reserva activa del alumno en el evento ligado', function () {
    $e = enlaceEscenario();
    $reserva = Reserva::create(['id_evento' => $e['evento']->id_evento, 'id_alumno' => $e['alumno']->id_alumno]);
    $bearer = enlaceBearer($e['tokenPlano']);

    $response = $this->withToken($bearer)->postJson('/api/game/sessions', ['id_evento' => $e['evento']->id_evento]);
    $response->assertCreated()->assertExactJson([
        'id_sesion' => $response->json('id_sesion'),
        'estatus' => 'en_progreso',
    ]);

    expect(SesionPractica::find($response->json('id_sesion'))->id_reserva)->toBe($reserva->id_reserva);
});

it('start sin reserva deja id_reserva null y responde idéntico (contrato congelado)', function () {
    $e = enlaceEscenario();
    $bearer = enlaceBearer($e['tokenPlano']);

    $response = $this->withToken($bearer)->postJson('/api/game/sessions', ['id_evento' => $e['evento']->id_evento]);
    $response->assertCreated()->assertExactJson([
        'id_sesion' => $response->json('id_sesion'),
        'estatus' => 'en_progreso',
    ]);

    expect(SesionPractica::find($response->json('id_sesion'))->id_reserva)->toBeNull();
});

it('no enlaza reserva cuando el body trae un evento distinto al ligado al token', function () {
    $e = enlaceEscenario();
    $otroEvento = EventoAgenda::create([
        'id_practica' => $e['evento']->id_practica, 'id_grupo' => $e['evento']->id_grupo,
        'fecha_hora_inicio' => now()->subMinutes(5), 'fecha_hora_fin' => now()->addHour(),
        'estatus' => 'programado', 'cupo_maximo' => 5,
    ]);
    Reserva::create(['id_evento' => $otroEvento->id_evento, 'id_alumno' => $e['alumno']->id_alumno]);
    $bearer = enlaceBearer($e['tokenPlano']);

    $response = $this->withToken($bearer)->postJson('/api/game/sessions', ['id_evento' => $otroEvento->id_evento]);

    $response->assertCreated();
    expect(SesionPractica::find($response->json('id_sesion'))->id_reserva)->toBeNull();
});
