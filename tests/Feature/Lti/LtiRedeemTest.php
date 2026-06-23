<?php
use App\Models\{Rol, Usuario, Alumno, Carrera, Materia, Practica, SesionPractica};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function crearSesionLti(): array {
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $usuario = Usuario::create([
        'id_rol' => $rolA->id_rol,
        'correo' => 'alumno.lti@test.com',
        'nombre' => 'Ana',
        'apellidos' => 'LTI',
        'lti_user_id' => 'lti-u-99',
    ]);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);
    $alumno = Alumno::create([
        'id_usuario' => $usuario->id_usuario,
        'id_carrera' => $carrera->id_carrera,
        'matricula' => '20250099',
        'semestre_actual' => 3,
        'generacion' => '2025',
    ]);
    $mat = Materia::create(['clave' => 'LTI', 'nombre' => 'Practica LTI', 'creditos' => 5]);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab LTI']);
    $sesion = SesionPractica::create([
        'id_practica' => $practica->id_practica,
        'id_evento' => null,
        'id_alumno' => $alumno->id_alumno,
        'fecha_inicio' => now(),
        'estatus' => 'en_progreso',
    ]);
    return [$sesion, $usuario];
}

it('canjea un token LTI valido y devuelve bearer con id_sesion', function () {
    [$sesion, $usuario] = crearSesionLti();

    $token = Str::random(64);
    Cache::put('lti_play_'.hash('sha256', $token), $sesion->id_sesion, now()->addHours(2));

    $r = $this->postJson('/api/game/lti-redeem', ['lti_session_token' => $token]);
    $r->assertOk()
      ->assertJsonStructure(['access_token', 'token_type', 'id_sesion', 'alumno', 'practica']);

    expect($r->json('token_type'))->toBe('Bearer');
    expect($r->json('id_sesion'))->toBe($sesion->id_sesion);
    // contrasena_hash must NOT be in the response (hidden on Usuario via $hidden)
    expect($r->getContent())->not->toContain('contrasena_hash');
});

it('rechaza la segunda llamada con el mismo token LTI (uso unico)', function () {
    [$sesion] = crearSesionLti();

    $token = Str::random(64);
    Cache::put('lti_play_'.hash('sha256', $token), $sesion->id_sesion, now()->addHours(2));

    // Primera llamada: exito
    $this->postJson('/api/game/lti-redeem', ['lti_session_token' => $token])->assertOk();
    // Segunda llamada: el cache ya fue consumido por pull, debe retornar 410
    $this->postJson('/api/game/lti-redeem', ['lti_session_token' => $token])->assertStatus(410);
});

it('rechaza un token LTI aleatorio con 410', function () {
    $this->postJson('/api/game/lti-redeem', ['lti_session_token' => Str::random(64)])
         ->assertStatus(410)
         ->assertJson(['message' => 'Token inválido o expirado']);
});
