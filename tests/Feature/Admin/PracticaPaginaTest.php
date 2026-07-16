<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('la pagina de practicas renderiza con el catalogo de juegos', function () {
    $rol = Rol::firstOrCreate(['nombre' => 'Admin']);
    $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'O']);

    $this->actingAs($u)
        ->get('/admin/practicas')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Practicas')
            ->has('juegos', 3)
            ->where('juegos.0.id', 'recolecta')
        );
});
