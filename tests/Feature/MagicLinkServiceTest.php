<?php
use App\Models\{Rol, Usuario, Materia, Maestro, CicloEscolar, Grupo, Practica, EventoAgenda, TokenJuego};
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('genera un magic link con token hasheado y expiracion default', function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'B']);
    $evento = crearEventoBasico();

    $res = app(MagicLinkService::class)->generar($usuario->id_usuario, $evento->id_evento);

    expect($res['token'])->toBeString()->not->toBeEmpty();
    $modelo = TokenJuego::first();
    expect($modelo->token_hash)->toBe(hash('sha256', $res['token']));
    expect($modelo->usado)->toBeFalse();
    // default 120 min: expira aprox en 2h
    $diff = abs($modelo->fecha_expiracion->diffInMinutes(now()));
    expect($diff)->toBeGreaterThan(115)->toBeLessThan(125);
    expect($res['deeplink'])->toContain('tecnm-metaverso://play?token=');
});
