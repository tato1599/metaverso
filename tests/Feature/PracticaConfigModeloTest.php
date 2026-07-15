<?php

use App\Models\Materia;
use App\Models\Practica;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('guarda y lee la config como array', function () {
    $m = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $p = Practica::create([
        'id_materia' => $m->id_materia, 'titulo' => 'P', 'orden' => 1,
        'escena_referencia' => 'recolecta', 'config' => ['meta_objetos' => 25],
    ]);

    expect($p->fresh()->config)->toBe(['meta_objetos' => 25]);
});

it('configResuelta mezcla lo guardado con los defaults del tipo', function () {
    $m = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $p = Practica::create([
        'id_materia' => $m->id_materia, 'titulo' => 'P', 'orden' => 1,
        'escena_referencia' => 'recolecta', 'config' => ['meta_objetos' => 25],
    ]);

    expect($p->configResuelta())
        ->toBe(['meta_objetos' => 25, 'tiempo_limite_seg' => 120, 'dificultad' => 'media']);
});

it('configResuelta devuelve solo defaults cuando config es null (practica heredada)', function () {
    $m = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $p = Practica::create([
        'id_materia' => $m->id_materia, 'titulo' => 'P', 'orden' => 1,
        'escena_referencia' => 'circuito', 'config' => null,
    ]);

    expect($p->configResuelta())
        ->toBe(['num_estaciones' => 4, 'en_orden' => false, 'tiempo_limite_seg' => 300]);
});
