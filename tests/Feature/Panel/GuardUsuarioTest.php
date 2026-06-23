<?php
use App\Models\{Rol, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('autentica un Usuario con el guard web y expone helpers de rol', function () {
    $rolMaestro = Rol::create(['nombre' => 'Maestro']);
    $rolAlumno = Rol::create(['nombre' => 'Alumno']);

    $maestro = Usuario::create(['id_rol' => $rolMaestro->id_rol, 'correo' => 'm@b.com', 'nombre' => 'M', 'apellidos' => 'X']);
    $alumno = Usuario::create(['id_rol' => $rolAlumno->id_rol, 'correo' => 'a@b.com', 'nombre' => 'A', 'apellidos' => 'Y']);

    $this->actingAs($maestro);
    expect(auth()->user()->id_usuario)->toBe($maestro->id_usuario);

    expect($maestro->esStaffPanel())->toBeTrue();
    expect($alumno->esStaffPanel())->toBeFalse();
    expect($maestro->esCoordinadorOAdmin())->toBeFalse();
});

it('marca Coordinador y Admin como staff y coord/admin', function () {
    foreach (['Coordinador','Admin'] as $n) {
        $rol = Rol::create(['nombre' => $n]);
        $u = Usuario::create(['id_rol' => $rol->id_rol, 'correo' => strtolower($n).'@b.com', 'nombre' => $n, 'apellidos' => 'Z']);
        expect($u->esStaffPanel())->toBeTrue();
        expect($u->esCoordinadorOAdmin())->toBeTrue();
    }
});
