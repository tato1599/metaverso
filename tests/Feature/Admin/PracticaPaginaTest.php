<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('la pagina de practicas renderiza con el registro de tipos', function () {
    $rol = Rol::firstOrCreate(['nombre' => 'Coordinador']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'c@b.com', 'nombre' => 'C', 'apellidos' => 'O']);

    $this->actingAs($u)
        ->get('/admin/practicas')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Practicas')
            ->has('registro', 3)
            ->where('registro.0.id', 'recolecta')
        );
});
