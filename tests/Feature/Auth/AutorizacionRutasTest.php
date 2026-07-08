<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

uses(RefreshDatabase::class);

function usuarioDeRol(string $rol): Usuario
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

it('redirige invitados a login en /mi/calendario', function () {
    $this->get('/mi/calendario')->assertRedirect('/login');
});

it('redirige invitados a login en /panel', function () {
    $this->get('/panel')->assertRedirect('/login');
});

it('permite alumno en su calendario', function () {
    $this->actingAs(usuarioDeRol('Alumno'))->get('/mi/calendario')->assertOk();
});

it('bloquea maestro en /mi/*', function () {
    $this->actingAs(usuarioDeRol('Maestro'))->get('/mi/calendario')->assertForbidden();
});

it('bloquea alumno en /panel', function () {
    $this->actingAs(usuarioDeRol('Alumno'))->get('/panel')->assertForbidden();
});

/*
 * Guardrail Sanctum (spec §2/§7): con sanctum.guard => ['web'], habilitar
 * statefulApi()/StartSession sobre /api haría que una sesión web pase
 * abilities:game vía TransientToken. Se verifica por invariantes del grupo de
 * middleware (un test cookie-only da falso verde con SESSION_DRIVER=array,
 * porque el session.store se comparte entre requests del mismo test).
 */
it('el grupo api no es stateful (guardrail Sanctum)', function () {
    $grupoApi = app('router')->getMiddlewareGroups()['api'];

    expect($grupoApi)->not->toContain(EnsureFrontendRequestsAreStateful::class)
        ->and($grupoApi)->not->toContain(StartSession::class);

    $this->getJson('/api/game/me')->assertUnauthorized();
});
