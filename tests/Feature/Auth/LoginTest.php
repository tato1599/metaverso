<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function crearUsuarioConRol(string $rol, array $attrs = []): Usuario
{
    $r = Rol::firstOrCreate(['nombre' => $rol]);

    return Usuario::create(array_merge([
        'id_rol' => $r->id_rol,
        'correo' => fake()->unique()->safeEmail(),
        'contrasena_hash' => Hash::make('secreto123'),
        'nombre' => 'Test',
        'apellidos' => 'User',
        'activo' => true,
    ], $attrs));
}

it('muestra la página de login', function () {
    $this->get('/login')->assertOk();
});

it('loguea a un alumno y redirige a su calendario', function () {
    $u = crearUsuarioConRol('Alumno');
    $this->post('/login', ['correo' => $u->correo, 'password' => 'secreto123'])
        ->assertRedirect('/mi/calendario');
    $this->assertAuthenticatedAs($u);
});

it('loguea a un maestro y redirige al panel', function () {
    $u = crearUsuarioConRol('Maestro');
    $this->post('/login', ['correo' => $u->correo, 'password' => 'secreto123'])
        ->assertRedirect('/panel');
});

it('redirige por rol aunque exista una URL intended de otro contexto', function () {
    $this->get('/panel'); // deja url.intended = /panel (tras Task 4)
    $u = crearUsuarioConRol('Alumno');
    $this->post('/login', ['correo' => $u->correo, 'password' => 'secreto123'])
        ->assertRedirect('/mi/calendario');
});

it('rechaza contraseña incorrecta', function () {
    $u = crearUsuarioConRol('Alumno');
    $this->from('/login')->post('/login', ['correo' => $u->correo, 'password' => 'mala'])
        ->assertRedirect('/login')->assertSessionHasErrors('correo');
    $this->assertGuest();
});

it('rechaza usuario inactivo', function () {
    $u = crearUsuarioConRol('Alumno', ['activo' => false]);
    $this->post('/login', ['correo' => $u->correo, 'password' => 'secreto123'])
        ->assertSessionHasErrors('correo');
    $this->assertGuest();
});

it('aplica rate limit tras 5 intentos fallidos', function () {
    $u = crearUsuarioConRol('Alumno');
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['correo' => $u->correo, 'password' => 'mala']);
    }
    $this->post('/login', ['correo' => $u->correo, 'password' => 'secreto123'])
        ->assertSessionHasErrors('correo');
    $this->assertGuest();
});

it('el rate limit distingue IPs detrás del proxy (trustProxies)', function () {
    $u = crearUsuarioConRol('Alumno');
    foreach (range(1, 5) as $i) {
        $this->withHeaders(['X-Forwarded-For' => '1.1.1.1'])
            ->post('/login', ['correo' => $u->correo, 'password' => 'mala']);
    }
    $this->withHeaders(['X-Forwarded-For' => '2.2.2.2'])
        ->post('/login', ['correo' => $u->correo, 'password' => 'secreto123']);
    $this->assertAuthenticatedAs($u);
});

it('redirige por rol a un usuario autenticado que visita /login', function () {
    $alumno = crearUsuarioConRol('Alumno');
    $this->actingAs($alumno)->get('/login')->assertRedirect('/mi/calendario');

    $maestro = crearUsuarioConRol('Maestro');
    $this->actingAs($maestro)->get('/login')->assertRedirect('/panel');
});

it('logout redirige a login para cualquier rol', function () {
    $u = crearUsuarioConRol('Alumno');
    $this->actingAs($u)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
