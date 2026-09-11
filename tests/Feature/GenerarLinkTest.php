<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('genera un link via API', function () {
    config(['metaverso.links_api_key' => 'test-key']);
    $rol = Rol::create(['nombre' => 'Alumno']);
    $usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'B']);
    $evento = crearEventoBasico();

    $r = $this->withHeader('X-Api-Key', 'test-key')
        ->postJson('/api/links', ['id_usuario' => $usuario->id_usuario, 'id_evento' => $evento->id_evento]);
    $r->assertCreated()->assertJsonStructure(['url', 'deeplink', 'expira']);
    expect($r->json('deeplink'))->toContain('tecnm-metaverso://play?token=');
    expect($r->json('expira'))->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});

it('la pagina /jugar muestra el boton de abrir el juego', function () {
    $this->get('/jugar/cualquier-token')->assertOk()->assertSee('Abrir el juego');
});

it('rechaza POST /api/links sin el header X-Api-Key con 401', function () {
    config(['metaverso.links_api_key' => 'test-key']);
    $rol = Rol::create(['nombre' => 'Alumno2']);
    $usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'b@c.com', 'nombre' => 'B', 'apellidos' => 'C']);
    $evento = crearEventoBasico();

    $this->postJson('/api/links', ['id_usuario' => $usuario->id_usuario, 'id_evento' => $evento->id_evento])
        ->assertStatus(401);
});
