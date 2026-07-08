<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // La suite debe pasar en un clon sin public/build (gitignored).
        $this->withoutVite();
    }
}
