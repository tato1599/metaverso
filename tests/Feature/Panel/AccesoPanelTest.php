<?php
use App\Models\{Rol, Usuario};
use Illuminate\Support\Facades\URL;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function urlAcceso(Usuario $u): string {
    return URL::temporarySignedRoute('panel.acceso', now()->addMinutes(30), ['usuario' => $u->id_usuario]);
}

it('inicia sesion con enlace firmado valido para un Maestro', function () {
    $rol = Rol::create(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);

    $this->get(urlAcceso($u))->assertRedirect(route('panel.dashboard'));
    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($u->id_usuario);
});

it('rechaza enlace con firma manipulada', function () {
    $rol = Rol::create(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $url = urlAcceso($u) . 'manipulado';
    $this->get($url)->assertForbidden();
    expect(auth()->check())->toBeFalse();
});

it('rechaza a un Alumno aunque el enlace sea valido', function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'Y']);
    $this->get(urlAcceso($u))->assertForbidden();
    expect(auth()->check())->toBeFalse();
});

it('el middleware panel bloquea acceso sin sesion', function () {
    $this->get(route('panel.dashboard'))->assertForbidden();
});
