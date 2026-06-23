<?php
use App\Models\{Rol, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('genera un link via API', function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'B']);
    $evento = crearEventoBasico();

    $r = $this->postJson('/api/links', ['id_usuario' => $usuario->id_usuario, 'id_evento' => $evento->id_evento]);
    $r->assertCreated()->assertJsonStructure(['url','deeplink','expira']);
    expect($r->json('deeplink'))->toContain('tecnm-metaverso://play?token=');
});

it('la pagina /jugar muestra el boton de abrir juego', function () {
    $this->get('/jugar/cualquier-token')->assertOk()->assertSee('Abrir juego');
});
