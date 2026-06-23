<?php
use App\Models\{Rol, Usuario, Alumno, Carrera, SesionPractica, Inscripcion};
use App\Services\MagicLinkService;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $this->usuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'B']);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);
    $this->alumno = Alumno::create(['id_usuario' => $this->usuario->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025']);
    $this->evento = crearEventoBasico();
    Inscripcion::create([
        'id_alumno' => $this->alumno->id_alumno,
        'id_grupo'  => $this->evento->id_grupo,
        'fecha_inscripcion' => now(),
        'estatus'   => 'activa',
    ]);
});

it('inicia y completa una sesion guardando calificacion y telemetria', function () {
    Sanctum::actingAs($this->usuario, ['game']);

    $start = $this->postJson('/api/game/sessions', ['id_evento' => $this->evento->id_evento]);
    $start->assertCreated()->assertJsonPath('estatus', 'en_progreso');
    $idSesion = $start->json('id_sesion');

    $done = $this->postJson("/api/game/sessions/{$idSesion}/complete", [
        'calificacion' => 87.5,
        'datos_resultado' => ['aciertos' => 9, 'errores' => 1],
    ]);
    $done->assertOk()->assertJsonPath('estatus', 'completada');

    $sesion = SesionPractica::find($idSesion);
    expect($sesion->calificacion)->toBe(87.5);
    expect($sesion->datos_resultado['aciertos'])->toBe(9);
    expect($sesion->fecha_fin)->not->toBeNull();
});

it('rechaza calificacion fuera de rango con 422', function () {
    Sanctum::actingAs($this->usuario, ['game']);
    $idSesion = $this->postJson('/api/game/sessions', ['id_evento' => $this->evento->id_evento])->json('id_sesion');
    $this->postJson("/api/game/sessions/{$idSesion}/complete", ['calificacion' => 150])->assertStatus(422);
});

it('impide completar la sesion de otro alumno con 403', function () {
    $idSesion = SesionPractica::create([
        'id_evento' => $this->evento->id_evento, 'id_alumno' => $this->alumno->id_alumno,
        'id_practica' => $this->evento->id_practica, 'fecha_inicio' => now(), 'estatus' => 'en_progreso',
    ])->id_sesion;

    $rol = Rol::create(['nombre' => 'Alumno2']);
    $otro = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'c@d.com', 'nombre' => 'C', 'apellidos' => 'D']);
    $carrera = Carrera::first();
    Alumno::create(['id_usuario' => $otro->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250002', 'semestre_actual' => 3, 'generacion' => '2025']);
    Sanctum::actingAs($otro, ['game']);

    $this->postJson("/api/game/sessions/{$idSesion}/complete", ['calificacion' => 80, 'datos_resultado' => []])->assertStatus(403);
});

it('rechaza completar dos veces la misma sesion con 409', function () {
    Sanctum::actingAs($this->usuario, ['game']);
    $idSesion = $this->postJson('/api/game/sessions', ['id_evento' => $this->evento->id_evento])->json('id_sesion');

    $this->postJson("/api/game/sessions/{$idSesion}/complete", ['calificacion' => 80, 'datos_resultado' => []])->assertOk();
    $this->postJson("/api/game/sessions/{$idSesion}/complete", ['calificacion' => 90, 'datos_resultado' => []])->assertStatus(409);
});

it('rechaza token sin ability game con 403', function () {
    Sanctum::actingAs($this->usuario, []);   // sin abilities
    $this->getJson('/api/game/me')->assertStatus(403);
});

it('rechaza start si el alumno no está inscrito en el grupo del evento con 403', function () {
    // $this->alumno is enrolled (from beforeEach), create a *different* alumno NOT enrolled
    $rol = Rol::create(['nombre' => 'AlumnoExtra']);
    $otroUsuario = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'x@y.com', 'nombre' => 'X', 'apellidos' => 'Y']);
    $carrera = Carrera::first();
    $otroAlumno = Alumno::create([
        'id_usuario' => $otroUsuario->id_usuario,
        'id_carrera' => $carrera->id_carrera,
        'matricula'  => '20259999',
        'semestre_actual' => 1,
        'generacion' => '2025',
    ]);

    Sanctum::actingAs($otroUsuario, ['game']);
    $this->postJson('/api/game/sessions', ['id_evento' => $this->evento->id_evento])
        ->assertStatus(403);
});
