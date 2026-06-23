<?php
use App\Lti\AprovisionarAlumno;
use App\Models\{Usuario, Alumno};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea el alumno la primera vez y lo reutiliza despues', function () {
    $svc = new AprovisionarAlumno();
    $a1 = $svc->desdeLaunch('moodle-user-9', 'Ana', 'Ruiz', 'ana@correo.com');
    expect($a1)->toBeInstanceOf(Alumno::class);
    expect(Usuario::where('lti_user_id', 'moodle-user-9')->count())->toBe(1);

    $a2 = $svc->desdeLaunch('moodle-user-9', 'Ana', 'Ruiz', 'ana@correo.com');
    expect($a2->id_alumno)->toBe($a1->id_alumno);
    expect(Alumno::count())->toBe(1);
});

it('genera correo deterministico si no hay correo', function () {
    $svc = new AprovisionarAlumno();
    $a = $svc->desdeLaunch('mu-5', 'Beto', 'Diaz', null);
    expect($a->usuario->correo)->toContain('lti+mu-5@');
});
