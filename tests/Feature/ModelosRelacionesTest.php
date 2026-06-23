<?php
use App\Models\{Usuario, Rol, Alumno, Carrera, EventoAgenda, TokenJuego, SesionPractica};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resuelve las relaciones clave', function () {
    $rol = Rol::create(['nombre' => 'Alumno']);
    $usuario = Usuario::create([
        'id_rol' => $rol->id_rol, 'correo' => 'a@b.com',
        'nombre' => 'Ana', 'apellidos' => 'Lopez',
    ]);
    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sistemas', 'duracion_semestres' => 9]);
    $alumno = Alumno::create([
        'id_usuario' => $usuario->id_usuario, 'id_carrera' => $carrera->id_carrera,
        'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025',
    ]);

    expect($usuario->rol->nombre)->toBe('Alumno');
    expect($alumno->usuario->correo)->toBe('a@b.com');
    expect($usuario->alumno->matricula)->toBe('20250001');
});
