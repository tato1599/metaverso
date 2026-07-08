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
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function admingiUsuario(string $rol): Usuario
{
    $r = Rol::firstOrCreate(['nombre' => $rol]);

    return Usuario::create([
        'id_rol' => $r->id_rol,
        'correo' => fake()->unique()->safeEmail(),
        'contrasena_hash' => Hash::make('x'),
        'nombre' => 'T',
        'apellidos' => 'U',
    ]);
}

function admingiGrupo(): Grupo
{
    $materia = Materia::create(['clave' => 'GI'.fake()->unique()->numberBetween(1, 99999), 'nombre' => 'Materia GI', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-30']);
    $maestro = Maestro::create([
        'id_usuario' => admingiUsuario('Maestro')->id_usuario,
        'numero_empleado' => 'GI'.fake()->unique()->numberBetween(1, 99999),
    ]);

    return Grupo::create([
        'id_materia' => $materia->id_materia,
        'id_maestro' => $maestro->id_maestro,
        'id_ciclo' => $ciclo->id_ciclo,
        'clave' => '3A',
        'cupo_maximo' => 30,
    ]);
}

function admingiAlumno(): Alumno
{
    $carrera = Carrera::firstOrCreate(['clave' => 'GIC'], ['nombre' => 'Carrera GI', 'duracion_semestres' => 9]);

    return Alumno::create([
        'id_usuario' => admingiUsuario('Alumno')->id_usuario,
        'id_carrera' => $carrera->id_carrera,
        'matricula' => (string) fake()->unique()->numberBetween(20250000, 20259999),
        'semestre_actual' => 1,
        'generacion' => '2025',
    ]);
}

it('protege /admin/grupos y el index entrega filas y catálogos', function () {
    $this->get('/admin/grupos')->assertRedirect('/login');
    $this->actingAs(admingiUsuario('Maestro'))->get('/admin/grupos')->assertForbidden();

    $grupo = admingiGrupo();
    admingiAlumno();

    $this->actingAs(admingiUsuario('Coordinador'))->get('/admin/grupos')->assertInertia(
        fn (Assert $page) => $page->component('Admin/Grupos')
            ->has('filas', 1, fn (Assert $fila) => $fila
                ->where('id_grupo', $grupo->id_grupo)
                ->where('clave', '3A')
                ->where('inscritos', 0)
                ->etc()
            )
            ->has('materias', 1)
            ->has('maestros', 1)
            ->has('ciclos', 1)
            ->has('alumnos', 1, fn (Assert $alumno) => $alumno->has('id_alumno')->has('etiqueta'))
    );
});

it('crea y actualiza grupos con validación', function () {
    $coord = admingiUsuario('Coordinador');
    $base = admingiGrupo();

    $this->actingAs($coord)->post('/admin/grupos', [
        'id_materia' => $base->id_materia,
        'id_maestro' => $base->id_maestro,
        'id_ciclo' => $base->id_ciclo,
        'clave' => '5B',
        'cupo_maximo' => 25,
    ])->assertRedirect();
    expect(Grupo::where('clave', '5B')->exists())->toBeTrue();

    $this->actingAs($coord)->postJson('/admin/grupos', [
        'id_materia' => 999999,
        'id_maestro' => $base->id_maestro,
        'id_ciclo' => $base->id_ciclo,
        'clave' => '',
        'cupo_maximo' => 0,
    ])->assertUnprocessable()->assertJsonValidationErrors(['id_materia', 'clave', 'cupo_maximo']);

    $this->actingAs($coord)->put("/admin/grupos/{$base->id_grupo}", [
        'id_materia' => $base->id_materia,
        'id_maestro' => $base->id_maestro,
        'id_ciclo' => $base->id_ciclo,
        'clave' => '3A',
        'cupo_maximo' => 40,
    ])->assertRedirect();
    expect($base->fresh()->cupo_maximo)->toBe(40);
});

it('bloquea eliminar grupo con inscripciones o eventos, y elimina uno libre', function () {
    $coord = admingiUsuario('Coordinador');

    $conInscripcion = admingiGrupo();
    Inscripcion::create([
        'id_alumno' => admingiAlumno()->id_alumno,
        'id_grupo' => $conInscripcion->id_grupo,
        'fecha_inscripcion' => today(),
        'estatus' => 'baja',
    ]);
    $this->actingAs($coord)->deleteJson("/admin/grupos/{$conInscripcion->id_grupo}")
        ->assertUnprocessable()->assertJsonValidationErrors('eliminar');

    $conEvento = admingiGrupo();
    $practica = Practica::create(['id_materia' => $conEvento->id_materia, 'titulo' => 'P1']);
    EventoAgenda::create([
        'id_practica' => $practica->id_practica,
        'id_grupo' => $conEvento->id_grupo,
        'fecha_hora_inicio' => '2026-03-01 10:00:00',
        'fecha_hora_fin' => '2026-03-01 12:00:00',
    ]);
    $this->actingAs($coord)->deleteJson("/admin/grupos/{$conEvento->id_grupo}")
        ->assertUnprocessable()->assertJsonValidationErrors('eliminar');

    $libre = admingiGrupo();
    $this->actingAs($coord)->delete("/admin/grupos/{$libre->id_grupo}")->assertRedirect();
    expect(Grupo::find($libre->id_grupo))->toBeNull();
});

it('inscribe un alumno con fecha de hoy y rechaza duplicado activo', function () {
    $coord = admingiUsuario('Coordinador');
    $grupo = admingiGrupo();
    $alumno = admingiAlumno();

    $this->actingAs($coord)->post("/admin/grupos/{$grupo->id_grupo}/inscripciones", [
        'id_alumno' => $alumno->id_alumno,
    ])->assertRedirect();

    $inscripcion = Inscripcion::where('id_alumno', $alumno->id_alumno)->where('id_grupo', $grupo->id_grupo)->sole();
    expect($inscripcion->estatus)->toBe('activa')
        ->and($inscripcion->fecha_inscripcion->toDateString())->toBe(today()->toDateString());

    $this->actingAs($coord)->postJson("/admin/grupos/{$grupo->id_grupo}/inscripciones", [
        'id_alumno' => $alumno->id_alumno,
    ])->assertUnprocessable()->assertJsonValidationErrors('id_alumno');
});

it('alta, baja y re-alta dejan UNA sola fila activa', function () {
    $coord = admingiUsuario('Coordinador');
    $grupo = admingiGrupo();
    $alumno = admingiAlumno();
    $this->actingAs($coord);

    $this->post("/admin/grupos/{$grupo->id_grupo}/inscripciones", ['id_alumno' => $alumno->id_alumno])->assertRedirect();
    $inscripcion = Inscripcion::where('id_alumno', $alumno->id_alumno)->where('id_grupo', $grupo->id_grupo)->sole();

    $this->delete("/admin/grupos/{$grupo->id_grupo}/inscripciones/{$inscripcion->id_inscripcion}")->assertRedirect();
    expect($inscripcion->fresh()->estatus)->toBe('baja');

    $this->post("/admin/grupos/{$grupo->id_grupo}/inscripciones", ['id_alumno' => $alumno->id_alumno])->assertRedirect();

    $filas = Inscripcion::where('id_alumno', $alumno->id_alumno)->where('id_grupo', $grupo->id_grupo)->get();
    expect($filas)->toHaveCount(1)
        ->and($filas->first()->estatus)->toBe('activa');
});

it('quitar deja la inscripción en baja sin borrar la fila y valida el grupo padre', function () {
    $coord = admingiUsuario('Coordinador');
    $grupo = admingiGrupo();
    $otroGrupo = admingiGrupo();
    $alumno = admingiAlumno();
    $this->actingAs($coord);

    $this->post("/admin/grupos/{$grupo->id_grupo}/inscripciones", ['id_alumno' => $alumno->id_alumno])->assertRedirect();
    $inscripcion = Inscripcion::where('id_alumno', $alumno->id_alumno)->where('id_grupo', $grupo->id_grupo)->sole();

    $this->delete("/admin/grupos/{$otroGrupo->id_grupo}/inscripciones/{$inscripcion->id_inscripcion}")->assertNotFound();
    expect($inscripcion->fresh()->estatus)->toBe('activa');

    $this->delete("/admin/grupos/{$grupo->id_grupo}/inscripciones/{$inscripcion->id_inscripcion}")->assertRedirect();
    expect(Inscripcion::count())->toBe(1)
        ->and($inscripcion->fresh()->estatus)->toBe('baja');
});
