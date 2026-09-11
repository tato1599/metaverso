<?php

use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Reserva;
use Database\Seeders\DemoAgendaSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * El seeder de agenda se corre sobre bases YA sembradas —la instancia local tiene
 * usuarios y contextos creados por launches reales de Moodle que no se pueden
 * perder— así que su idempotencia no es un detalle: es el requisito.
 */
it('enriquece la demo con un segundo grupo y varias prácticas', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoAgendaSeeder::class);

    expect(Grupo::count())->toBe(2)
        ->and(EventoAgenda::count())->toBeGreaterThan(2)
        ->and(Reserva::where('estatus', 'activa')->count())->toBeGreaterThan(0);
});

it('es idempotente: correrlo dos veces no duplica nada', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoAgendaSeeder::class);

    $antes = [Grupo::count(), EventoAgenda::count(), Reserva::count()];
    $this->seed(DemoAgendaSeeder::class);

    expect([Grupo::count(), EventoAgenda::count(), Reserva::count()])->toBe($antes);
});

it('no siembra dos reservas activas de la misma práctica para un alumno', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoAgendaSeeder::class);

    $duplicadas = Reserva::where('estatus', 'activa')
        ->whereHas('evento', fn ($q) => $q->where('fecha_hora_fin', '>=', now()))
        ->with('evento')
        ->get()
        ->groupBy(fn (Reserva $r) => $r->id_alumno.'-'.$r->evento->id_practica)
        ->filter(fn ($grupo) => $grupo->count() > 1);

    expect($duplicadas)->toBeEmpty();
});
