<?php

it('demo disponible en entorno de pruebas y sin api key prellenada', function () {
    config(['metaverso.links_api_key' => 'clave-super-secreta']);
    $this->get('/demo')->assertOk()->assertDontSee('clave-super-secreta');
});

it('demo oculta en producción', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->get('/demo')->assertNotFound();
});
