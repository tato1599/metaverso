<?php
use App\Lti\AgsCliente;
use App\Models\{Rol, Usuario, Alumno, Carrera, Materia, Practica, SesionPractica, LtiPlatform};
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sesionLti(bool $conAgs): array {
    $rol = Rol::firstOrCreate(['nombre' => 'Alumno']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a'.rand(1,99999).'@c.com', 'nombre' => 'A', 'apellidos' => 'B', 'lti_user_id' => 'mu-'.rand(1,99999)]);
    $car = Carrera::firstOrCreate(['clave' => 'LTI'], ['nombre' => 'Externa', 'duracion_semestres' => 1]);
    $al = Alumno::create(['id_usuario' => $u->id_usuario, 'id_carrera' => $car->id_carrera, 'matricula' => 'M'.rand(1,99999), 'semestre_actual' => 1, 'generacion' => '2026']);
    $mat = Materia::create(['clave' => 'C'.rand(1,99999), 'nombre' => 'M', 'creditos' => 5]);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab']);
    $plat = LtiPlatform::create(['issuer' => 'http://localhost:8080', 'client_id' => 'C', 'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x', 'deployment_id' => 'D', 'activo' => true]);
    $sesion = SesionPractica::create([
        'id_practica' => $practica->id_practica, 'id_evento' => null, 'id_alumno' => $al->id_alumno,
        'fecha_inicio' => now(), 'estatus' => 'en_progreso',
        'lti_platform_id' => $plat->id,
        'ags_lineitem_url' => $conAgs ? 'http://localhost:8080/.../lineitem' : null,
        'ags_endpoint' => $conAgs ? 'http://localhost:8080/mod/lti/services.php' : null,
    ]);
    return [$u, $sesion];
}

it('envia la calificacion por AGS al completar una sesion LTI', function () {
    [$u, $sesion] = sesionLti(conAgs: true);
    $mock = Mockery::mock(AgsCliente::class);
    $mock->shouldReceive('enviar')->once()->with(Mockery::on(fn ($s) => $s->id_sesion === $sesion->id_sesion));
    $this->app->instance(AgsCliente::class, $mock);

    Sanctum::actingAs($u, ['game']);
    $this->postJson("/api/game/sessions/{$sesion->id_sesion}/complete", ['calificacion' => 90, 'datos_resultado' => []])
        ->assertOk();
});

it('NO llama AGS si la sesion no es LTI (sin lineitem)', function () {
    [$u, $sesion] = sesionLti(conAgs: false);
    $mock = Mockery::mock(AgsCliente::class);
    $mock->shouldReceive('enviar')->never();
    $this->app->instance(AgsCliente::class, $mock);

    Sanctum::actingAs($u, ['game']);
    $this->postJson("/api/game/sessions/{$sesion->id_sesion}/complete", ['calificacion' => 70, 'datos_resultado' => []])
        ->assertOk();
});
