<?php
use App\Models\{Rol, Usuario, Carrera, Materia, Maestro, CicloEscolar, Grupo, Practica, EventoAgenda, TokenJuego};
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearEventoBasico(): EventoAgenda {
    $rol = Rol::create(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $u->id_usuario, 'numero_empleado' => 'E1']);
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $prac = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab 1']);
    return EventoAgenda::create([
        'id_practica' => $prac->id_practica, 'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(),
    ]);
}

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
    expect(abs($modelo->fecha_expiracion->diffInMinutes(now())))->toBeGreaterThan(115);
    expect($res['deeplink'])->toContain('tecnm-metaverso://play?token=');
});
