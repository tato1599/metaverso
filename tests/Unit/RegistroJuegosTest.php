<?php

use App\Support\RegistroJuegos;
use Tests\TestCase;

uses(TestCase::class);

it('lista los juegos como id + label', function () {
    $juegos = RegistroJuegos::juegos();
    expect(collect($juegos)->pluck('id')->all())->toBe(['recolecta', 'ensambla', 'circuito'])
        ->and($juegos[0])->toHaveKeys(['id', 'label']);
});

it('reconoce los ids validos y rechaza los desconocidos', function () {
    expect(RegistroJuegos::existe('recolecta'))->toBeTrue()
        ->and(RegistroJuegos::existe('Lab_viejo'))->toBeFalse();
});

it('expone los ids para validar', function () {
    expect(RegistroJuegos::ids())->toBe(['recolecta', 'ensambla', 'circuito']);
});
