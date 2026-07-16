<?php

use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\CicloEscolar;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\MateriaCarrera;
use App\Models\Practica;
use App\Models\Rol;
use App\Models\SesionPractica;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function adminmpUsuario(string $rol): Usuario
{
    $r = Rol::firstOrCreate(['nombre' => $rol]);

    return Usuario::create([
        'id_rol' => $r->id_rol,
        'correo' => fake()->unique()->safeEmail(),
        'contrasena_hash' => Hash::make('x'),
        'nombre' => 'T',
        'apellidos' => 'U',
        'activo' => true,
    ]);
}

function adminmpCarrera(): Carrera
{
    return Carrera::create([
        'clave' => 'C'.fake()->unique()->numberBetween(100, 999),
        'nombre' => 'Carrera '.fake()->unique()->word(),
        'duracion_semestres' => 9,
    ]);
}

function adminmpMateria(array $extra = []): Materia
{
    return Materia::create(array_merge([
        'clave' => 'M'.fake()->unique()->numberBetween(100, 999),
        'nombre' => 'Materia '.fake()->unique()->word(),
        'creditos' => 5,
    ], $extra));
}

function adminmpPractica(Materia $materia, array $extra = []): Practica
{
    return Practica::create(array_merge([
        'id_materia' => $materia->id_materia,
        'titulo' => 'Práctica '.fake()->unique()->word(),
        'orden' => 1,
        'escena_referencia' => 'recolecta',
    ], $extra));
}

function adminmpGrupo(Materia $materia): Grupo
{
    $maestro = Maestro::create([
        'id_usuario' => adminmpUsuario('Maestro')->id_usuario,
        'numero_empleado' => 'E'.fake()->unique()->numberBetween(1000, 9999),
    ]);
    $ciclo = CicloEscolar::create([
        'nombre' => '2026-'.fake()->unique()->numberBetween(1, 999),
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-06-30',
    ]);

    return Grupo::create([
        'id_materia' => $materia->id_materia,
        'id_maestro' => $maestro->id_maestro,
        'id_ciclo' => $ciclo->id_ciclo,
        'clave' => '3A',
        'cupo_maximo' => 30,
    ]);
}

function adminmpAlumno(): Alumno
{
    return Alumno::create([
        'id_usuario' => adminmpUsuario('Alumno')->id_usuario,
        'id_carrera' => adminmpCarrera()->id_carrera,
        'matricula' => (string) fake()->unique()->numberBetween(20250000, 20259999),
        'semestre_actual' => 1,
        'generacion' => '2025',
    ]);
}

function adminmpSesion(Practica $practica): SesionPractica
{
    return SesionPractica::create([
        'id_alumno' => adminmpAlumno()->id_alumno,
        'id_practica' => $practica->id_practica,
        'fecha_inicio' => now(),
        'estatus' => 'completada',
    ]);
}

function adminmpEvento(Practica $practica): EventoAgenda
{
    return EventoAgenda::create([
        'id_practica' => $practica->id_practica,
        'id_grupo' => adminmpGrupo($practica->materia)->id_grupo,
        'fecha_hora_inicio' => now()->addDay(),
        'fecha_hora_fin' => now()->addDay()->addHour(),
    ]);
}

it('protege materias y prácticas: invitado a login, maestro 403, coordinador entra', function () {
    $this->get('/admin/materias')->assertRedirect('/login');
    $this->get('/admin/practicas')->assertRedirect('/login');

    $maestro = adminmpUsuario('Maestro');
    $this->actingAs($maestro)->get('/admin/materias')->assertForbidden();
    $this->actingAs($maestro)->get('/admin/practicas')->assertForbidden();

    $coord = adminmpUsuario('Coordinador');
    $this->actingAs($coord)->get('/admin/materias')->assertInertia(
        fn (Assert $page) => $page->component('Admin/Materias')->has('filas')->has('carreras')
    );
});

it('crea materia con asignaciones a carreras y re-sincroniza el semestre', function () {
    $coord = adminmpUsuario('Coordinador');
    $c1 = adminmpCarrera();
    $c2 = adminmpCarrera();

    $this->actingAs($coord)->post('/admin/materias', [
        'clave' => 'PROG1',
        'nombre' => 'Programación I',
        'creditos' => 8,
        'carreras' => [
            ['id_carrera' => $c1->id_carrera, 'semestre' => 1],
            ['id_carrera' => $c2->id_carrera, 'semestre' => 2],
        ],
    ])->assertRedirect();

    $materia = Materia::where('clave', 'PROG1')->firstOrFail();
    expect($materia->carreras)->toHaveCount(2)
        ->and($materia->carreras->firstWhere('id_carrera', $c1->id_carrera)->pivot->semestre)->toBe(1);

    $this->actingAs($coord)->put("/admin/materias/{$materia->id_materia}", [
        'clave' => 'PROG1',
        'nombre' => 'Programación I',
        'creditos' => 8,
        'carreras' => [
            ['id_carrera' => $c1->id_carrera, 'semestre' => 3],
        ],
    ])->assertRedirect();

    $materia->load('carreras');
    expect($materia->carreras)->toHaveCount(1)
        ->and($materia->carreras->first()->pivot->semestre)->toBe(3)
        ->and(MateriaCarrera::where('id_materia', $materia->id_materia)->count())->toBe(1);
});

it('rechaza clave de materia duplicada', function () {
    $coord = adminmpUsuario('Coordinador');
    adminmpMateria(['clave' => 'FIS1']);

    $this->actingAs($coord)->postJson('/admin/materias', [
        'clave' => 'FIS1',
        'nombre' => 'Física',
        'creditos' => 6,
    ])->assertUnprocessable()->assertJsonValidationErrors('clave');
});

it('elimina materia con solo pivote (detach) y bloquea si tiene grupos o prácticas', function () {
    $coord = adminmpUsuario('Coordinador');

    $soloPivote = adminmpMateria();
    $soloPivote->carreras()->attach(adminmpCarrera()->id_carrera, ['semestre' => 4]);
    $this->actingAs($coord)->delete("/admin/materias/{$soloPivote->id_materia}")->assertRedirect();
    expect(Materia::find($soloPivote->id_materia))->toBeNull()
        ->and(MateriaCarrera::where('id_materia', $soloPivote->id_materia)->count())->toBe(0);

    $conGrupo = adminmpMateria();
    adminmpGrupo($conGrupo);
    $this->actingAs($coord)->deleteJson("/admin/materias/{$conGrupo->id_materia}")
        ->assertUnprocessable()->assertJsonValidationErrors('eliminar');

    $conPractica = adminmpMateria();
    adminmpPractica($conPractica);
    $this->actingAs($coord)->deleteJson("/admin/materias/{$conPractica->id_materia}")
        ->assertUnprocessable()->assertJsonValidationErrors('eliminar');
});

it('crea una práctica y el index usa la página de prácticas', function () {
    $admin = adminmpUsuario('Admin');
    $materia = adminmpMateria();

    $this->actingAs($admin)->post('/admin/practicas', [
        'id_materia' => $materia->id_materia,
        'titulo' => 'Soldadura básica',
        'descripcion' => 'Práctica introductoria',
        'objetivos' => 'Identificar el equipo y soldar una unión simple',
        'orden' => 1,
        'duracion_estimada' => 45,
        'escena_referencia' => 'ensambla',
    ])->assertRedirect();

    $practica = Practica::where('titulo', 'Soldadura básica')->firstOrFail();
    expect($practica->id_materia)->toBe($materia->id_materia)
        ->and($practica->orden)->toBe(1)
        ->and($practica->escena_referencia)->toBe('ensambla');

    $this->actingAs($admin)->get('/admin/practicas')->assertInertia(
        fn (Assert $page) => $page->component('Admin/Practicas')
            ->where('titulo', 'Prácticas')
            ->has('filas', 1)
            ->where('filas.0.materia_nombre', $materia->nombre)
    );
});

it('bloquea eliminar práctica con eventos y permite eliminarla libre', function () {
    $admin = adminmpUsuario('Admin');

    $conEvento = adminmpPractica(adminmpMateria());
    adminmpEvento($conEvento);
    $this->actingAs($admin)->deleteJson("/admin/practicas/{$conEvento->id_practica}")
        ->assertUnprocessable()->assertJsonValidationErrors('eliminar');

    $libre = adminmpPractica(adminmpMateria());
    $this->actingAs($admin)->delete("/admin/practicas/{$libre->id_practica}")->assertRedirect();
    expect(Practica::find($libre->id_practica))->toBeNull();
});

it('bloquea eliminar práctica con sesiones registradas', function () {
    $admin = adminmpUsuario('Admin');

    $conSesion = adminmpPractica(adminmpMateria());
    adminmpSesion($conSesion);

    $this->actingAs($admin)->deleteJson("/admin/practicas/{$conSesion->id_practica}")
        ->assertUnprocessable()->assertJsonValidationErrors('eliminar');
    expect(Practica::find($conSesion->id_practica))->not->toBeNull();
});

it('bloquea cambiar la materia de una práctica con eventos o sesiones', function () {
    $admin = adminmpUsuario('Admin');
    $otraMateria = adminmpMateria();
    $payload = fn (Materia $materia) => [
        'id_materia' => $materia->id_materia,
        'titulo' => 'Práctica movida',
        'orden' => 1,
        'escena_referencia' => 'recolecta',
    ];

    $conEvento = adminmpPractica(adminmpMateria());
    adminmpEvento($conEvento);
    $this->actingAs($admin)->putJson("/admin/practicas/{$conEvento->id_practica}", $payload($otraMateria))
        ->assertUnprocessable()->assertJsonValidationErrors('id_materia');
    expect($conEvento->fresh()->id_materia)->not->toBe($otraMateria->id_materia);

    $conSesion = adminmpPractica(adminmpMateria());
    adminmpSesion($conSesion);
    $this->actingAs($admin)->putJson("/admin/practicas/{$conSesion->id_practica}", $payload($otraMateria))
        ->assertUnprocessable()->assertJsonValidationErrors('id_materia');
    expect($conSesion->fresh()->id_materia)->not->toBe($otraMateria->id_materia);

    $libre = adminmpPractica(adminmpMateria());
    $this->actingAs($admin)->put("/admin/practicas/{$libre->id_practica}", $payload($otraMateria))->assertRedirect();
    expect($libre->fresh()->id_materia)->toBe($otraMateria->id_materia);
});

it('rechaza carreras duplicadas en la asignación de una materia', function () {
    $coord = adminmpUsuario('Coordinador');
    $carrera = adminmpCarrera();

    $this->actingAs($coord)->postJson('/admin/materias', [
        'clave' => 'DUP1',
        'nombre' => 'Materia duplicada',
        'creditos' => 5,
        'carreras' => [
            ['id_carrera' => $carrera->id_carrera, 'semestre' => 1],
            ['id_carrera' => $carrera->id_carrera, 'semestre' => 2],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('carreras.0.id_carrera');

    expect(Materia::where('clave', 'DUP1')->exists())->toBeFalse();
});
