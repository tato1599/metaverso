<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('la raiz muestra la portada, no un redirect a login ni la plantilla de Laravel', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Metaverso Escolar')
        ->assertSee('metaverso')
        ->assertDontSee('Laravel');
});

it('la portada enlaza al panel', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('panel.dashboard'), false);
});
