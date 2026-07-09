<?php

use App\Models\CicloEscolar;
use App\Models\Espacio;
use App\Models\EventoAgenda;
use App\Models\Grupo;
use App\Models\Maestro;
use App\Models\Materia;
use App\Models\Practica;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function admincatUsuario(string $rol): Usuario
{
    $r = Rol::firstOrCreate(['nombre' => $rol]);

    return Usuario::create([
        'id_rol' => $r->id_rol,
        'correo' => fake()->unique()->safeEmail(),
        'contrasena_hash' => Hash::make('x'),
        'nombre' => 'T',
        'apellidos' => 'U',
        'activo' => true,
    ]);
}

function admincatCiclo(string $nombre = '2026-1'): CicloEscolar
{
    return CicloEscolar::create([
        'nombre' => $nombre,
        'fecha_inicio' => '2026-01-12',
        'fecha_fin' => '2026-06-26',
        'activo' => true,
    ]);
}

function admincatGrupoEn(CicloEscolar $ciclo): Grupo
{
    $materia = Materia::create(['clave' => 'CAT-'.fake()->unique()->numberBetween(1, 9999), 'nombre' => 'Materia', 'creditos' => 5]);
    $maestro = Maestro::create(['id_usuario' => admincatUsuario('Maestro')->id_usuario, 'numero_empleado' => fake()->unique()->numerify('EMP####')]);

    return Grupo::create([
        'id_materia' => $materia->id_materia,
        'id_maestro' => $maestro->id_maestro,
        'id_ciclo' => $ciclo->id_ciclo,
        'clave' => '3A',
        'cupo_maximo' => 30,
    ]);
}

function admincatEventoEn(Espacio $espacio): EventoAgenda
{
    $grupo = admincatGrupoEn(admincatCiclo(fake()->unique()->numerify('ciclo-####')));
    $practica = Practica::create(['id_materia' => $grupo->id_materia, 'titulo' => 'Práctica 1', 'orden' => 1]);

    return EventoAgenda::create([
        'id_practica' => $practica->id_practica,
        'id_grupo' => $grupo->id_grupo,
        'id_espacio' => $espacio->id_espacio,
        'fecha_hora_inicio' => '2026-03-02 10:00:00',
        'fecha_hora_fin' => '2026-03-02 12:00:00',
    ]);
}

it('protege ciclos y espacios: invitado a login y maestro 403', function () {
    $this->get('/admin/ciclos')->assertRedirect('/login');
    $this->get('/admin/espacios')->assertRedirect('/login');

    $maestro = admincatUsuario('Maestro');
    $this->actingAs($maestro)->get('/admin/ciclos')->assertForbidden();
    $this->actingAs($maestro)->get('/admin/espacios')->assertForbidden();
});

it('muestra los índices de ciclos y espacios con la página genérica', function () {
    $coord = admincatUsuario('Coordinador');
    admincatCiclo();
    Espacio::create(['nombre' => 'Lab A', 'tipo' => 'fisico', 'capacidad' => 25]);

    $this->actingAs($coord)->get('/admin/ciclos')->assertInertia(
        fn (Assert $page) => $page->component('Admin/Recurso')
            ->where('titulo', 'Ciclos escolares')
            ->has('filas', 1)
            ->where('filas.0.fecha_inicio', '2026-01-12')
    );
    $this->actingAs($coord)->get('/admin/espacios')->assertInertia(
        fn (Assert $page) => $page->component('Admin/Recurso')
            ->where('titulo', 'Espacios')
            ->has('filas', 1)
    );
});

it('el coordinador crea ciclos y espacios', function () {
    $coord = admincatUsuario('Coordinador');

    $this->actingAs($coord)->post('/admin/ciclos', [
        'nombre' => '2026-2', 'fecha_inicio' => '2026-08-10', 'fecha_fin' => '2026-12-18', 'activo' => true,
    ])->assertRedirect();
    expect(CicloEscolar::where('nombre', '2026-2')->exists())->toBeTrue();

    $this->actingAs($coord)->post('/admin/espacios', [
        'nombre' => 'Sala VR', 'tipo' => 'virtual', 'capacidad' => null,
    ])->assertRedirect();
    expect(Espacio::where('nombre', 'Sala VR')->where('tipo', 'virtual')->exists())->toBeTrue();
});

it('rechaza un nombre de ciclo duplicado', function () {
    $coord = admincatUsuario('Coordinador');
    admincatCiclo('2026-1');

    $this->actingAs($coord)->postJson('/admin/ciclos', [
        'nombre' => '2026-1', 'fecha_inicio' => '2026-01-12', 'fecha_fin' => '2026-06-26',
    ])->assertUnprocessable()->assertJsonValidationErrors('nombre');
});

it('rechaza fechas de ciclo invertidas', function () {
    $coord = admincatUsuario('Coordinador');

    $this->actingAs($coord)->postJson('/admin/ciclos', [
        'nombre' => '2027-1', 'fecha_inicio' => '2027-06-26', 'fecha_fin' => '2027-01-12',
    ])->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
});

it('actualiza un ciclo sin chocar con su propio nombre', function () {
    $coord = admincatUsuario('Coordinador');
    $ciclo = admincatCiclo('2026-1');

    $this->actingAs($coord)->put("/admin/ciclos/{$ciclo->id_ciclo}", [
        'nombre' => '2026-1', 'fecha_inicio' => '2026-01-12', 'fecha_fin' => '2026-07-03', 'activo' => false,
    ])->assertRedirect();

    $ciclo->refresh();
    expect($ciclo->fecha_fin->toDateString())->toBe('2026-07-03')
        ->and($ciclo->activo)->toBeFalse();
});

it('bloquea eliminar un ciclo con grupos y permite eliminar uno libre', function () {
    $coord = admincatUsuario('Coordinador');

    $conGrupos = admincatCiclo('2026-1');
    admincatGrupoEn($conGrupos);
    $this->actingAs($coord)->deleteJson("/admin/ciclos/{$conGrupos->id_ciclo}")
        ->assertUnprocessable()->assertJsonValidationErrors('eliminar');
    expect(CicloEscolar::find($conGrupos->id_ciclo))->not->toBeNull();

    $libre = admincatCiclo('2026-2');
    $this->actingAs($coord)->delete("/admin/ciclos/{$libre->id_ciclo}")->assertRedirect();
    expect(CicloEscolar::find($libre->id_ciclo))->toBeNull();
});

it('bloquea eliminar un espacio con eventos y permite eliminar uno libre', function () {
    $coord = admincatUsuario('Coordinador');

    $conEventos = Espacio::create(['nombre' => 'Lab ocupado', 'tipo' => 'fisico', 'capacidad' => 20]);
    admincatEventoEn($conEventos);
    $this->actingAs($coord)->deleteJson("/admin/espacios/{$conEventos->id_espacio}")
        ->assertUnprocessable()->assertJsonValidationErrors('eliminar');
    expect(Espacio::find($conEventos->id_espacio))->not->toBeNull();

    $libre = Espacio::create(['nombre' => 'Lab libre', 'tipo' => 'fisico', 'capacidad' => 10]);
    $this->actingAs($coord)->delete("/admin/espacios/{$libre->id_espacio}")->assertRedirect();
    expect(Espacio::find($libre->id_espacio))->toBeNull();
});
