<?php

use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Materia;
use App\Models\MateriaCarrera;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function admincarrUsuario(string $rol, bool $activo = true): Usuario
{
    $r = Rol::firstOrCreate(['nombre' => $rol]);

    return Usuario::create([
        'id_rol' => $r->id_rol,
        'correo' => fake()->unique()->safeEmail(),
        'contrasena_hash' => Hash::make('x'),
        'nombre' => 'T',
        'apellidos' => 'U',
        'activo' => $activo,
    ]);
}

it('protege /admin: invitado a login, maestro y alumno 403, coordinador entra', function () {
    $this->get('/admin/carreras')->assertRedirect('/login');
    $this->actingAs(admincarrUsuario('Maestro'))->get('/admin/carreras')->assertForbidden();
    $this->actingAs(admincarrUsuario('Alumno'))->get('/admin/carreras')->assertForbidden();
    $this->actingAs(admincarrUsuario('Coordinador'))->get('/admin/carreras')->assertInertia(
        fn (Assert $page) => $page->component('Admin/Recurso')->where('titulo', 'Carreras')
    );
});

it('un usuario desactivado pierde acceso en las tres áreas', function () {
    $coordInactivo = admincarrUsuario('Coordinador', activo: false);
    $this->actingAs($coordInactivo)->get('/admin/carreras')->assertForbidden();
    $this->actingAs($coordInactivo)->get('/panel')->assertForbidden();

    $alumnoInactivo = admincarrUsuario('Alumno', activo: false);
    $this->actingAs($alumnoInactivo)->get('/mi/calendario')->assertForbidden();
});

it('crea y actualiza carreras con clave única', function () {
    $coord = admincarrUsuario('Coordinador');

    $this->actingAs($coord)->post('/admin/carreras', [
        'clave' => 'ISC', 'nombre' => 'Sistemas', 'duracion_semestres' => 9,
    ])->assertRedirect();

    $this->actingAs($coord)->postJson('/admin/carreras', [
        'clave' => 'ISC', 'nombre' => 'Otra', 'duracion_semestres' => 8,
    ])->assertStatus(422)->assertJsonValidationErrors('clave');

    $carrera = Carrera::where('clave', 'ISC')->first();
    $this->actingAs($coord)->put("/admin/carreras/{$carrera->id_carrera}", [
        'clave' => 'ISC', 'nombre' => 'Ing. en Sistemas', 'duracion_semestres' => 9,
    ])->assertRedirect();
    expect($carrera->fresh()->nombre)->toBe('Ing. en Sistemas');
});

it('bloquea eliminar carrera con alumnos o con materias asignadas', function () {
    $coord = admincarrUsuario('Coordinador');

    $conAlumnos = Carrera::create(['clave' => 'CA', 'nombre' => 'Con alumnos', 'duracion_semestres' => 9]);
    $uA = admincarrUsuario('Alumno');
    Alumno::create(['id_usuario' => $uA->id_usuario, 'id_carrera' => $conAlumnos->id_carrera, 'matricula' => '20250001', 'semestre_actual' => 1, 'generacion' => '2025']);
    $this->actingAs($coord)->deleteJson("/admin/carreras/{$conAlumnos->id_carrera}")
        ->assertStatus(422)->assertJsonValidationErrors('eliminar');

    $conMaterias = Carrera::create(['clave' => 'CM', 'nombre' => 'Con materias', 'duracion_semestres' => 9]);
    $materia = Materia::create(['clave' => 'MAT-1', 'nombre' => 'Prog', 'creditos' => 5]);
    MateriaCarrera::create(['id_materia' => $materia->id_materia, 'id_carrera' => $conMaterias->id_carrera, 'semestre' => 3]);
    $this->actingAs($coord)->deleteJson("/admin/carreras/{$conMaterias->id_carrera}")
        ->assertStatus(422)->assertJsonValidationErrors('eliminar');

    $libre = Carrera::create(['clave' => 'LB', 'nombre' => 'Libre', 'duracion_semestres' => 9]);
    $this->actingAs($coord)->delete("/admin/carreras/{$libre->id_carrera}")->assertRedirect();
    expect(Carrera::find($libre->id_carrera))->toBeNull();
});
