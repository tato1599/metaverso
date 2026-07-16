<?php

use App\Models\Materia;
use App\Models\Practica;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usuarioRol(string $rol, string $correo): Usuario
{
    $r = Rol::firstOrCreate(['nombre' => $rol]);

    return Usuario::create(['id_rol' => $r->id_rol, 'correo' => $correo, 'nombre' => 'N', 'apellidos' => 'A']);
}

function materiaBase(): Materia
{
    return Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
}

it('el admin enlaza una practica con un juego de Godot valido', function () {
    $m = materiaBase();
    $this->actingAs(usuarioRol('Admin', 'a@b.com'))
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'ensambla',
        ])->assertSessionHasNoErrors();

    expect(Practica::sole()->escena_referencia)->toBe('ensambla');
});

it('rechaza un juego que no esta en el catalogo', function () {
    $m = materiaBase();
    $this->actingAs(usuarioRol('Admin', 'a@b.com'))
        ->post('/admin/practicas', [
            'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1,
            'escena_referencia' => 'Lab_Inexistente',
        ])->assertSessionHasErrors('escena_referencia');

    expect(Practica::count())->toBe(0);
});

it('un coordinador no puede ver ni administrar practicas (solo Admin)', function () {
    $m = materiaBase();
    $coord = usuarioRol('Coordinador', 'c@b.com');

    $this->actingAs($coord)->get('/admin/practicas')->assertForbidden();

    $this->actingAs($coord)->post('/admin/practicas', [
        'id_materia' => $m->id_materia, 'titulo' => 'P1', 'orden' => 1, 'escena_referencia' => 'recolecta',
    ])->assertForbidden();

    expect(Practica::count())->toBe(0);
});
