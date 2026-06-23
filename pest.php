<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDeprecationHandling;
use Pest\Concerns\Foundational;
use Pest\Plugins\Environment;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is `Tests\TestCase`. Of course, you may change it using
| the `uses()` function to bind a different classes or traits.
|
*/

uses(
    Tests\TestCase::class,
    InteractsWithDeprecationHandling::class,
    Foundational::class,
)
->in('tests');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "macros" that you can use to assert that
| your code behaves as expected. Of course, you may extend the Expect API at any time.
|
*/

expect()->extend('toBeWithinRange', function (int $min, int $max) {
    return $this->toBeGreaterThanOrEqual($min)
        ->toBeLessThanOrEqual($max);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Pest allows you to define custom helper functions used throughout your
| tests using the `function()` helper from Pest. Of course, you may
| add additional helper functions as you wish.
|
*/

function app(): Application
{
    return resolve(Application::class);
}
