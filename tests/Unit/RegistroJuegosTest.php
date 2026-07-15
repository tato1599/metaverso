<?php

use App\Support\RegistroJuegos;
use Tests\TestCase;

uses(TestCase::class);

it('reconoce los tres tipos y rechaza los desconocidos', function () {
    expect(RegistroJuegos::existe('recolecta'))->toBeTrue()
        ->and(RegistroJuegos::existe('ensambla'))->toBeTrue()
        ->and(RegistroJuegos::existe('circuito'))->toBeTrue()
        ->and(RegistroJuegos::existe('Lab_Variables'))->toBeFalse();
    expect(collect(RegistroJuegos::tipos())->pluck('id')->all())
        ->toBe(['recolecta', 'ensambla', 'circuito']);
});

it('devuelve los defaults de un tipo', function () {
    expect(RegistroJuegos::defaults('recolecta'))
        ->toBe(['meta_objetos' => 10, 'tiempo_limite_seg' => 120, 'dificultad' => 'media']);
});

it('resuelve la config poniendo lo guardado encima de los defaults', function () {
    $r = RegistroJuegos::resolver('recolecta', ['meta_objetos' => 25]);
    expect($r)->toBe(['meta_objetos' => 25, 'tiempo_limite_seg' => 120, 'dificultad' => 'media']);
});

it('resuelve solo con defaults cuando la config es null', function () {
    expect(RegistroJuegos::resolver('circuito', null))
        ->toBe(['num_estaciones' => 4, 'en_orden' => false, 'tiempo_limite_seg' => 300]);
});

it('ignora claves ajenas al esquema del tipo al resolver', function () {
    $r = RegistroJuegos::resolver('recolecta', ['meta_objetos' => 5, 'hackeo' => 1]);
    expect($r)->not->toHaveKey('hackeo');
});

it('da reglas de validacion por parametro del tipo', function () {
    $reglas = RegistroJuegos::reglasConfig('recolecta');
    expect($reglas)->toHaveKeys(['config.meta_objetos', 'config.tiempo_limite_seg', 'config.dificultad']);
});
