<?php
use App\Models\{Rol, Alumno, EventoAgenda};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\DemoSeeder;

uses(RefreshDatabase::class);

it('siembra datos demo coherentes', function () {
    $this->seed(DemoSeeder::class);
    expect(Rol::count())->toBe(4);
    expect(Alumno::count())->toBe(3);
    expect(EventoAgenda::count())->toBe(1);
});
