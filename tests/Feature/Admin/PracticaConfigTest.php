<?php

use App\Models\Materia;
use App\Models\Practica;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function coordinador(): Usuario
{
    $rol = Rol::firstOrCreate(['nombre' => 'Coordinador']);

    return Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'c@b.com', 'nombre' => 'C', 'apellidos' => 'O']);
}

function materiaBase(): Materia
{
    return Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
}

it('crea una practica con tipo valido y config valida', function () {
    $m = materiaBase();
    $this->actingAs(coordinador())
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'recolecta',
            'config' => ['meta_objetos' => 15, 'tiempo_limite_seg' => 100, 'dificultad' => 'dificil'],
        ])->assertSessionHasNoErrors();

    // toEqual (no toBe): la columna es jsonb y Postgres normaliza el orden de
    // las claves del objeto al guardarlo, así que el orden no es significativo.
    expect(Practica::sole()->config)
        ->toEqual(['meta_objetos' => 15, 'tiempo_limite_seg' => 100, 'dificultad' => 'dificil']);
});

it('crea una practica ensambla con checkbox reintentos en false', function () {
    $m = materiaBase();
    $this->actingAs(coordinador())
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'ensambla',
            'config' => ['num_piezas' => 8, 'tiempo_limite_seg' => 180, 'reintentos' => false],
        ])->assertSessionHasNoErrors();

    expect(Practica::sole()->config)
        ->toEqual(['num_piezas' => 8, 'tiempo_limite_seg' => 180, 'reintentos' => false]);
});

it('crea una practica circuito con checkbox en_orden en false', function () {
    $m = materiaBase();
    $this->actingAs(coordinador())
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'circuito',
            'config' => ['num_estaciones' => 3, 'en_orden' => false, 'tiempo_limite_seg' => 300],
        ])->assertSessionHasNoErrors();

    expect(Practica::sole()->config)
        ->toEqual(['num_estaciones' => 3, 'en_orden' => false, 'tiempo_limite_seg' => 300]);
});

it('rechaza un tipo de juego desconocido', function () {
    $m = materiaBase();
    $this->actingAs(coordinador())
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'Lab_Inexistente',
            'config' => [],
        ])->assertSessionHasErrors('escena_referencia');

    expect(Practica::count())->toBe(0);
});

it('rechaza un parametro fuera de rango', function () {
    $m = materiaBase();
    $this->actingAs(coordinador())
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'recolecta',
            'config' => ['meta_objetos' => 0, 'tiempo_limite_seg' => 100, 'dificultad' => 'media'],
        ])->assertSessionHasErrors('config.meta_objetos');

    expect(Practica::count())->toBe(0);
});

it('rechaza una opcion fuera del enum', function () {
    $m = materiaBase();
    $this->actingAs(coordinador())
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'recolecta',
            'config' => ['meta_objetos' => 5, 'tiempo_limite_seg' => 100, 'dificultad' => 'imposible'],
        ])->assertSessionHasErrors('config.dificultad');
});
