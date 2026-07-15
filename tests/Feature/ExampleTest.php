<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_muestra_la_portada(): void
    {
        // La raíz sirve la portada pública; el detalle se cubre en PortadaTest.
        $this->get('/')->assertOk()->assertSee('Metaverso Escolar');
    }
}
