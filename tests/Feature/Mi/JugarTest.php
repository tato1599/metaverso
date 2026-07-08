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
use App\Models\TokenJuego;
use App\Models\Usuario;
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Evento EN VENTANA por default, con alumno inscrito y reserva activa.
 *
 * @return array{usuario: Usuario, alumno: Alumno, grupo: Grupo, evento: EventoAgenda, reserva: Reserva}
 */
function jugarEscenario(array $eventoAttrs = [], string $estatusInscripcion = 'activa', ?string $estatusReserva = 'activa'): array
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
        'fecha_hora_inicio' => now()->subMinutes(5), 'fecha_hora_fin' => now()->addHour(),
        'estatus' => 'programado', 'cupo_maximo' => 5,
    ], $eventoAttrs));

    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $u = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => fake()->unique()->safeEmail(), 'contrasena_hash' => Hash::make('x'), 'nombre' => 'A', 'apellidos' => 'L', 'activo' => true]);
    $carrera = Carrera::firstOrCreate(['clave' => 'ISC'], ['nombre' => 'ISC', 'duracion_semestres' => 9]);
    $alumno = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => fake()->unique()->numerify('2025####'), 'semestre_actual' => 3, 'generacion' => '2025']);
    if ($estatusInscripcion !== null) {
        Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => $estatusInscripcion]);
    }
    $reserva = null;
    if ($estatusReserva !== null) {
        $reserva = Reserva::create(['id_evento' => $evento->id_evento, 'id_alumno' => $alumno->id_alumno, 'estatus' => $estatusReserva]);
    }

    return ['usuario' => $u, 'alumno' => $alumno, 'grupo' => $grupo, 'evento' => $evento, 'reserva' => $reserva];
}

it('genera el enlace y redirige al lanzador con reserva activa en ventana', function () {
    $e = jugarEscenario();
    $response = $this->actingAs($e['usuario'])->post("/mi/eventos/{$e['evento']->id_evento}/jugar");

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/jugar/');
    expect(TokenJuego::where('id_usuario', $e['usuario']->id_usuario)->where('usado', false)->count())->toBe(1);

    // Amarre extremo a extremo: el token generado abre sesión de juego real
    // y el bearer queda ligado al evento vía ability.
    $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
    $this->postJson('/api/game/redeem', ['token' => $token])->assertOk();
    expect($e['usuario']->tokens()->latest('id')->first()->abilities)
        ->toBe(['game', 'evento:'.$e['evento']->id_evento]);
});

it('en visita Inertia responde 409 con X-Inertia-Location (cruce a Blade)', function () {
    $e = jugarEscenario();
    $response = $this->actingAs($e['usuario'])
        ->withHeaders(['X-Inertia' => 'true'])
        ->post("/mi/eventos/{$e['evento']->id_evento}/jugar");

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))->toContain('/jugar/');
});

it('rechaza sin reserva y con reserva cancelada', function () {
    $sinReserva = jugarEscenario(estatusReserva: null);
    $this->actingAs($sinReserva['usuario'])
        ->post("/mi/eventos/{$sinReserva['evento']->id_evento}/jugar")->assertForbidden();

    $cancelada = jugarEscenario(estatusReserva: 'cancelada');
    $this->actingAs($cancelada['usuario'])
        ->post("/mi/eventos/{$cancelada['evento']->id_evento}/jugar")->assertForbidden();
});

it('rechaza al alumno con inscripción en baja aunque tenga reserva activa', function () {
    $e = jugarEscenario(estatusInscripcion: 'baja');
    $this->actingAs($e['usuario'])
        ->post("/mi/eventos/{$e['evento']->id_evento}/jugar")->assertForbidden();
});

it('rechaza fuera de la ventana del evento', function () {
    $antes = jugarEscenario([
        'fecha_hora_inicio' => now()->addHour(),
        'fecha_hora_fin' => now()->addHours(2),
    ]);
    $this->actingAs($antes['usuario'])
        ->postJson("/mi/eventos/{$antes['evento']->id_evento}/jugar")->assertStatus(422);

    $despues = jugarEscenario([
        'fecha_hora_inicio' => now()->subHours(2),
        'fecha_hora_fin' => now()->subHour(),
    ]);
    $this->actingAs($despues['usuario'])
        ->postJson("/mi/eventos/{$despues['evento']->id_evento}/jugar")->assertStatus(422);
});

it('rechaza evento cancelado dentro de ventana', function () {
    $e = jugarEscenario(['estatus' => 'cancelado']);
    $this->actingAs($e['usuario'])
        ->postJson("/mi/eventos/{$e['evento']->id_evento}/jugar")->assertStatus(422);
});

it('invalida los tokens previos del par usuario/evento, incluidos los del maestro', function () {
    $e = jugarEscenario();
    // Token emitido "por el maestro" vía panel/API: mismo par (usuario, evento).
    $previo = app(MagicLinkService::class)->generar($e['usuario']->id_usuario, $e['evento']->id_evento);

    $this->actingAs($e['usuario'])->post("/mi/eventos/{$e['evento']->id_evento}/jugar")->assertRedirect();

    expect($previo['modelo']->fresh()->usado)->toBeTrue();
    expect(TokenJuego::where('id_usuario', $e['usuario']->id_usuario)
        ->where('id_evento', $e['evento']->id_evento)
        ->where('usado', false)->count())->toBe(1);
});

it('aplica throttle propio: el 11º intento da 429 y reservas no consume este bucket', function () {
    $e = jugarEscenario(estatusReserva: null); // 403 baratos que sí cuentan para el limiter

    // 10 posts a reservas NO deben consumir el bucket de jugar.
    foreach (range(1, 10) as $i) {
        $this->actingAs($e['usuario'])->postJson('/mi/reservas', []);
    }

    foreach (range(1, 10) as $i) {
        $this->actingAs($e['usuario'])
            ->post("/mi/eventos/{$e['evento']->id_evento}/jugar")->assertForbidden();
    }
    $this->actingAs($e['usuario'])
        ->post("/mi/eventos/{$e['evento']->id_evento}/jugar")->assertStatus(429);
});
