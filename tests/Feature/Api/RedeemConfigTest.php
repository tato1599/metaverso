<?php
use App\Models\{Rol, Usuario, Alumno, Carrera, Maestro, Materia, CicloEscolar, Grupo, Practica, EventoAgenda};
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearEventoConPractica(string $escenaReferencia, ?array $config): EventoAgenda {
    $rol = Rol::create(['nombre' => 'Maestro']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $maestro = Maestro::create(['id_usuario' => $u->id_usuario, 'numero_empleado' => 'E1']);
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
    $prac = Practica::create([
        'id_materia' => $mat->id_materia,
        'titulo' => 'Lab 1',
        'escena_referencia' => $escenaReferencia,
        'config' => $config,
    ]);

    return EventoAgenda::create([
        'id_practica' => $prac->id_practica, 'id_grupo' => $grupo->id_grupo,
        'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(),
    ]);
}

function crearUsuarioAlumno(): Usuario {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'B']);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);
    Alumno::create(['id_usuario' => $usuario->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025']);

    return $usuario;
}

it('redeem incluye escena_referencia y config resuelta de la practica', function () {
    $usuario = crearUsuarioAlumno();
    $evento = crearEventoConPractica('recolecta', ['meta_objetos' => 15]);

    $res = app(MagicLinkService::class)->generar($usuario->id_usuario, $evento->id_evento);

    $r = $this->postJson('/api/game/redeem', ['token' => $res['token']])->assertOk();

    $r->assertJsonPath('practica.escena_referencia', 'recolecta');
    expect($r->json('practica.config'))
        ->toBe(['meta_objetos' => 15, 'tiempo_limite_seg' => 120, 'dificultad' => 'media']);
});

it('una practica sin config resuelve defaults en el redeem', function () {
    $usuario = crearUsuarioAlumno();
    $evento = crearEventoConPractica('circuito', null);

    $res = app(MagicLinkService::class)->generar($usuario->id_usuario, $evento->id_evento);

    $r = $this->postJson('/api/game/redeem', ['token' => $res['token']])->assertOk();

    $r->assertJsonPath('practica.escena_referencia', 'circuito');
    expect($r->json('practica.config'))
        ->toBe(['num_estaciones' => 4, 'en_orden' => false, 'tiempo_limite_seg' => 300]);
});
