<?php
use App\Models\{Rol, Usuario, Alumno, Carrera};
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $this->usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'B']);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);
    Alumno::create(['id_usuario' => $this->usuario->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025']);
    $this->evento = crearEventoBasico(); // helper de Task 5 (en Pest.php)
});

it('canjea un token valido y devuelve bearer', function () {
    $res = app(MagicLinkService::class)->generar($this->usuario->id_usuario, $this->evento->id_evento);
    $r = $this->postJson('/api/game/redeem', ['token' => $res['token']]);
    $r->assertOk()
      ->assertJsonStructure(['access_token','token_type','alumno' => ['matricula'],'practica','evento']);
    expect($res['modelo']->fresh()->usado)->toBeTrue();
});

it('rechaza token inexistente con 401', function () {
    $this->postJson('/api/game/redeem', ['token' => 'noexiste'])->assertStatus(401);
});

it('rechaza token expirado con 410', function () {
    $res = app(MagicLinkService::class)->generar($this->usuario->id_usuario, $this->evento->id_evento);
    $res['modelo']->update(['fecha_expiracion' => now()->subMinute()]);
    $this->postJson('/api/game/redeem', ['token' => $res['token']])->assertStatus(410);
});

it('rechaza token ya usado con 410', function () {
    $res = app(MagicLinkService::class)->generar($this->usuario->id_usuario, $this->evento->id_evento);
    $res['modelo']->update(['usado' => true, 'fecha_uso' => now()]);
    $this->postJson('/api/game/redeem', ['token' => $res['token']])->assertStatus(410);
});
