<?php
use App\Models\{Rol, Usuario, Maestro, Materia, CicloEscolar, Grupo};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function staff(string $rolNombre): Usuario {
    $rol = Rol::firstOrCreate(['nombre' => $rolNombre]);
    return Usuario::create(['id_rol' => $rol->id_rol, 'correo' => strtolower($rolNombre).rand(1,99999).'@b.com', 'nombre' => $rolNombre, 'apellidos' => 'T']);
}

function grupoDe(?Maestro $maestro = null): Grupo {
    $mat = Materia::create(['clave' => 'M'.rand(1,99999), 'nombre' => 'Mat', 'creditos' => 5]);
    $ciclo = CicloEscolar::create(['nombre' => '2026-1', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-01']);
    if (! $maestro) {
        $u = staff('Maestro');
        $maestro = Maestro::create(['id_usuario' => $u->id_usuario, 'numero_empleado' => 'E'.rand(1,99999)]);
    }
    return Grupo::create(['id_materia' => $mat->id_materia, 'id_maestro' => $maestro->id_maestro, 'id_ciclo' => $ciclo->id_ciclo, 'clave' => '3A', 'cupo_maximo' => 30]);
}

it('un Maestro ve solo sus grupos', function () {
    $uMaestro = staff('Maestro');
    $maestro = Maestro::create(['id_usuario' => $uMaestro->id_usuario, 'numero_empleado' => 'EMPX']);
    $mio = grupoDe($maestro);
    $ajeno = grupoDe(); // otro maestro

    $this->actingAs($uMaestro);
    $r = $this->get(route('panel.dashboard'));
    $r->assertOk()->assertSee($mio->clave);
    $r->assertDontSee('id_grupo_ajeno_marker'); // no aplica; comprobamos por conteo abajo
    expect($r->viewData('grupos')->pluck('id_grupo'))->toContain($mio->id_grupo);
    expect($r->viewData('grupos')->pluck('id_grupo'))->not->toContain($ajeno->id_grupo);
});

it('un Coordinador ve todos los grupos', function () {
    $g1 = grupoDe(); $g2 = grupoDe();
    $coord = staff('Coordinador');
    $this->actingAs($coord);
    $r = $this->get(route('panel.dashboard'));
    $r->assertOk();
    $ids = $r->viewData('grupos')->pluck('id_grupo');
    expect($ids)->toContain($g1->id_grupo)->toContain($g2->id_grupo);
});
