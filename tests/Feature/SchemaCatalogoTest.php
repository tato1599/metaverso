<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('crea las tablas del catalogo academico', function () {
    foreach (['roles','usuarios','alumnos','maestros','carreras','materias','materia_carrera','ciclos_escolares','espacios'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue();
    }
    expect(Schema::hasColumns('usuarios', ['id_rol','correo','contrasena_hash','nombre','apellidos','activo']))->toBeTrue();
    expect(Schema::hasColumns('alumnos', ['id_usuario','id_carrera','matricula','semestre_actual','generacion']))->toBeTrue();
});
