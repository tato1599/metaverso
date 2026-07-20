<?php
use App\Models\{Rol, Alumno, EventoAgenda, Inscripcion, CicloEscolar};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\DemoSeeder;

uses(RefreshDatabase::class);

it('siembra datos demo coherentes', function () {
    $this->seed(DemoSeeder::class);
    expect(Rol::count())->toBe(4);
    expect(Alumno::count())->toBe(3);
    expect(EventoAgenda::count())->toBe(2);

    expect(Inscripcion::count())->toBe(3);
    $evento = EventoAgenda::first();
    expect($evento->id_practica)->not->toBeNull();
    expect($evento->id_grupo)->not->toBeNull();
    expect(CicloEscolar::where('activo', true)->count())->toBe(1);
});
