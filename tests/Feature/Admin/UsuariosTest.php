<?php

use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Maestro;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function adminusrRol(string $nombre): Rol
{
    return Rol::firstOrCreate(['nombre' => $nombre]);
}

function adminusrUsuario(string $rol, bool $activo = true): Usuario
{
    return Usuario::create([
        'id_rol' => adminusrRol($rol)->id_rol,
        'correo' => fake()->unique()->safeEmail(),
        'contrasena_hash' => Hash::make('x'),
        'nombre' => 'T',
        'apellidos' => 'U',
        'activo' => $activo,
    ]);
}

function adminusrCarrera(): Carrera
{
    return Carrera::create([
        'clave' => 'U'.fake()->unique()->numberBetween(100, 999),
        'nombre' => 'Carrera '.fake()->unique()->word(),
        'duracion_semestres' => 9,
    ]);
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function adminusrPayloadAlumno(Carrera $carrera, array $extra = []): array
{
    return array_merge([
        'correo' => 'alumno.nuevo@test.mx',
        'nombre' => 'Ana',
        'apellidos' => 'López',
        'id_rol' => adminusrRol('Alumno')->id_rol,
        'password' => 'clave-segura-1',
        'matricula' => '20260001',
        'id_carrera' => $carrera->id_carrera,
        'semestre_actual' => 3,
        'generacion' => '2026',
    ], $extra);
}

it('protege /admin/usuarios: invitado a login, maestro 403, coordinador ve la página sin hashes', function () {
    $this->get('/admin/usuarios')->assertRedirect('/login');
    $this->actingAs(adminusrUsuario('Maestro'))->get('/admin/usuarios')->assertForbidden();
    $this->actingAs(adminusrUsuario('Coordinador'))->get('/admin/usuarios')->assertInertia(
        fn (Assert $page) => $page->component('Admin/Usuarios')
            ->where('titulo', 'Usuarios')
            ->has('filas', 2)
            ->missing('filas.0.contrasena_hash')
            ->missing('filas.0.lti_user_id')
            ->has('roles')
            ->has('carreras')
    );
});

it('crea un alumno completo: usuario y alumno quedan en BD', function () {
    $coord = adminusrUsuario('Coordinador');
    $carrera = adminusrCarrera();

    $this->actingAs($coord)->post('/admin/usuarios', adminusrPayloadAlumno($carrera))->assertRedirect();

    $usuario = Usuario::where('correo', 'alumno.nuevo@test.mx')->first();
    expect($usuario)->not->toBeNull()
        ->and($usuario->id_rol)->toBe(adminusrRol('Alumno')->id_rol);

    $alumno = Alumno::where('id_usuario', $usuario->id_usuario)->first();
    expect($alumno)->not->toBeNull()
        ->and($alumno->matricula)->toBe('20260001')
        ->and($alumno->id_carrera)->toBe($carrera->id_carrera)
        ->and($alumno->semestre_actual)->toBe(3)
        ->and($alumno->generacion)->toBe('2026');
});

it('exige los datos de alumno cuando el rol es Alumno', function () {
    $coord = adminusrUsuario('Coordinador');

    $this->actingAs($coord)->postJson('/admin/usuarios', [
        'correo' => 'incompleto@test.mx',
        'nombre' => 'Sin',
        'apellidos' => 'Datos',
        'id_rol' => adminusrRol('Alumno')->id_rol,
        'password' => 'clave-segura-1',
    ])->assertStatus(422)->assertJsonValidationErrors(['matricula', 'id_carrera', 'semestre_actual', 'generacion']);

    expect(Usuario::where('correo', 'incompleto@test.mx')->exists())->toBeFalse();
});

it('crea un maestro y rechaza numero_empleado duplicado', function () {
    $coord = adminusrUsuario('Coordinador');

    $this->actingAs($coord)->post('/admin/usuarios', [
        'correo' => 'maestro.nuevo@test.mx',
        'nombre' => 'Mario',
        'apellidos' => 'Pérez',
        'id_rol' => adminusrRol('Maestro')->id_rol,
        'password' => 'clave-segura-1',
        'numero_empleado' => 'EMP-001',
    ])->assertRedirect();

    $usuario = Usuario::where('correo', 'maestro.nuevo@test.mx')->first();
    $maestro = Maestro::where('id_usuario', $usuario->id_usuario)->first();
    expect($maestro)->not->toBeNull()->and($maestro->numero_empleado)->toBe('EMP-001');

    $this->actingAs($coord)->postJson('/admin/usuarios', [
        'correo' => 'otro.maestro@test.mx',
        'nombre' => 'Otro',
        'apellidos' => 'Maestro',
        'id_rol' => adminusrRol('Maestro')->id_rol,
        'password' => 'clave-segura-1',
        'numero_empleado' => 'EMP-001',
    ])->assertStatus(422)->assertJsonValidationErrors('numero_empleado');
});

it('rechaza id_rol basura (no numérico o arreglo) con 422 sin tronar', function () {
    $coord = adminusrUsuario('Coordinador');

    $this->actingAs($coord)->postJson('/admin/usuarios', [
        'correo' => 'basura1@test.mx',
        'nombre' => 'Rol',
        'apellidos' => 'Basura',
        'id_rol' => 'abc',
        'password' => 'clave-segura-1',
    ])->assertStatus(422)->assertJsonValidationErrors('id_rol');

    $this->actingAs($coord)->postJson('/admin/usuarios', [
        'correo' => 'basura2@test.mx',
        'nombre' => 'Rol',
        'apellidos' => 'Basura',
        'id_rol' => [1, 2],
        'password' => 'clave-segura-1',
    ])->assertStatus(422)->assertJsonValidationErrors('id_rol');

    expect(Usuario::whereIn('correo', ['basura1@test.mx', 'basura2@test.mx'])->exists())->toBeFalse();
});

it('rechaza correo duplicado al crear', function () {
    $coord = adminusrUsuario('Coordinador');
    $existente = adminusrUsuario('Maestro');

    $this->actingAs($coord)->postJson('/admin/usuarios', [
        'correo' => $existente->correo,
        'nombre' => 'Dup',
        'apellidos' => 'Licado',
        'id_rol' => adminusrRol('Coordinador')->id_rol,
        'password' => 'clave-segura-1',
    ])->assertStatus(422)->assertJsonValidationErrors('correo');
});

it('actualiza datos básicos sin permitir cambiar el rol', function () {
    $coord = adminusrUsuario('Coordinador');
    $otro = adminusrUsuario('Maestro');

    $this->actingAs($coord)->put("/admin/usuarios/{$otro->id_usuario}", [
        'correo' => 'correo.nuevo@test.mx',
        'nombre' => 'Nombre',
        'apellidos' => 'Nuevo',
        'activo' => true,
        'id_rol' => adminusrRol('Alumno')->id_rol,
    ])->assertRedirect();

    $otro->refresh();
    expect($otro->correo)->toBe('correo.nuevo@test.mx')
        ->and($otro->nombre)->toBe('Nombre')
        ->and($otro->id_rol)->toBe(adminusrRol('Maestro')->id_rol);
});

it('nadie puede desactivarse a sí mismo', function () {
    $coord = adminusrUsuario('Coordinador');

    $this->actingAs($coord)->putJson("/admin/usuarios/{$coord->id_usuario}", [
        'correo' => $coord->correo,
        'nombre' => 'T',
        'apellidos' => 'U',
        'activo' => false,
    ])->assertStatus(422)->assertJsonValidationErrors('activo');

    expect($coord->fresh()->activo)->toBeTrue();
});

it('desactiva a otro usuario y resetea su contraseña opcionalmente', function () {
    $coord = adminusrUsuario('Coordinador');
    $otro = adminusrUsuario('Maestro');

    $this->actingAs($coord)->put("/admin/usuarios/{$otro->id_usuario}", [
        'correo' => $otro->correo,
        'nombre' => 'T',
        'apellidos' => 'U',
        'activo' => false,
        'password' => 'nueva-clave-99',
    ])->assertRedirect();

    $otro->refresh();
    expect($otro->activo)->toBeFalse()
        ->and(Hash::check('nueva-clave-99', $otro->contrasena_hash))->toBeTrue();
});

it('ignora claves extra de mass-assignment en el update', function () {
    $coord = adminusrUsuario('Coordinador');
    $otro = adminusrUsuario('Maestro');
    $otro->update(['lti_user_id' => 'lti-original']);
    $hashOriginal = $otro->contrasena_hash;

    $this->actingAs($coord)->put("/admin/usuarios/{$otro->id_usuario}", [
        'correo' => $otro->correo,
        'nombre' => 'T',
        'apellidos' => 'U',
        'activo' => true,
        'contrasena_hash' => 'hash-inyectado',
        'lti_user_id' => 'lti-inyectado',
    ])->assertRedirect();

    $otro->refresh();
    expect($otro->contrasena_hash)->toBe($hashOriginal)
        ->and($otro->lti_user_id)->toBe('lti-original');
});

it('destroy siempre bloquea: los usuarios se desactivan, no se eliminan', function () {
    $coord = adminusrUsuario('Coordinador');
    $otro = adminusrUsuario('Maestro');

    $this->actingAs($coord)->deleteJson("/admin/usuarios/{$otro->id_usuario}")
        ->assertStatus(422)->assertJsonValidationErrors('eliminar');

    expect(Usuario::find($otro->id_usuario))->not->toBeNull();
});

it('el usuario creado puede iniciar sesión con la contraseña asignada', function () {
    $coord = adminusrUsuario('Coordinador');
    $carrera = adminusrCarrera();

    $this->actingAs($coord)->post('/admin/usuarios', adminusrPayloadAlumno($carrera))->assertRedirect();

    $this->post('/logout');
    $this->assertGuest();

    $this->post('/login', [
        'correo' => 'alumno.nuevo@test.mx',
        'password' => 'clave-segura-1',
    ])->assertRedirect(route('mi.calendario'));

    $this->assertAuthenticatedAs(Usuario::where('correo', 'alumno.nuevo@test.mx')->first());
});
