<?php

use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\CicloEscolar;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Practica;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('un Maestro abre su grupo y ve alumnos y eventos; un grupo ajeno da 403', function () {
    $rolM = Rol::firstOrCreate(['nombre' => 'Maestro']);
    $rolA = Rol::firstOrCreate(['nombre' => 'Alumno']);

    $uMaestro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'm@b.com', 'nombre' => 'Lau', 'apellidos' => 'G']);
    $maestro = Maestro::create(['id_usuario' => $uMaestro->id_usuario, 'numero_empleado' => 'EMP1']);

    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    $grupo = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);

    $carrera = Carrera::create(['clave' => 'ISC', 'nombre' => 'Sis', 'duracion_semestres' => 9]);
    $uAlumno = Usuario::create(['id_rol' => $rolA->id_rol, 'correo' => 'ana@b.com', 'nombre' => 'Ana', 'apellidos' => 'R']);
    $alumno = Alumno::create(['id_usuario' => $uAlumno->id_usuario, 'id_carrera' => $carrera->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 3, 'generacion' => '2025']);
    Inscripcion::create(['id_alumno' => $alumno->id_alumno, 'id_grupo' => $grupo->id_grupo, 'fecha_inscripcion' => now(), 'estatus' => 'activa']);

    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab 1']);
    $evento = EventoAgenda::create(['id_practica' => $practica->id_practica, 'id_grupo' => $grupo->id_grupo, 'fecha_hora_inicio' => now(), 'fecha_hora_fin' => now()->addHour(), 'estatus' => 'programado']);

    $this->actingAs($uMaestro);
    $this->get(route('panel.grupos.show', $grupo->id_grupo))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Panel/Grupo')
        ->where('grupo.id_grupo', $grupo->id_grupo)
        ->where('grupo.clave', '3A')
        ->has('alumnos', 1)
        ->where('alumnos.0.matricula', '20250001')
        ->where('alumnos.0.nombre', 'Ana R')
        ->has('eventos', 1)
        ->where('eventos.0.id_evento', $evento->id_evento)
        ->where('eventos.0.practica', 'Lab 1')
        ->has('csrf')
    );

    // grupo de otro maestro
    $uOtro = Usuario::create(['id_rol' => $rolM->id_rol, 'correo' => 'otro@b.com', 'nombre' => 'Otro', 'apellidos' => 'M']);
    $otroMaestro = Maestro::create(['id_usuario' => $uOtro->id_usuario, 'numero_empleado' => 'EMP2']);
    $grupoAjeno = Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $otroMaestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '5B', 'cupo_maximo' => 30]);
    $this->get(route('panel.grupos.show', $grupoAjeno->id_grupo))->assertForbidden();
});
