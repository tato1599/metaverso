<?php

it('la página /demo devuelve 200 y muestra los elementos esperados', function () {
    $response = $this->get('/demo');

    $response->assertOk();
    $response->assertSee('Demo en vivo');
    $response->assertSee('mermaid');
    $response->assertSee('Metaverso Escolar TecNM');
    $response->assertSee('/api/links');
});
