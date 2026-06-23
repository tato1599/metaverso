<?php
use App\Lti\DeepLinkRespondedor;
use App\Models\{Materia, Practica};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el selector lista las practicas', function () {
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab Uno']);
    Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab Dos']);

    $this->get(route('lti.deeplink'))->assertOk()->assertSee('Lab Uno')->assertSee('Lab Dos');
});

it('al elegir una practica responde con el JWT auto-posteado a la plataforma', function () {
    $mat = Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);
    $practica = Practica::create(['id_materia' => $mat->id_materia, 'titulo' => 'Lab Uno']);

    $this->app->bind(DeepLinkRespondedor::class, fn () => new class implements DeepLinkRespondedor {
        public function construir(int $idPractica): array {
            return ['jwt' => 'JWT-FAKE-'.$idPractica, 'returnUrl' => 'http://localhost:8080/mod/lti/return.php'];
        }
    });

    $r = $this->post(route('lti.deeplink.responder'), ['id_practica' => $practica->id_practica]);
    $r->assertOk()
        ->assertSee('JWT-FAKE-'.$practica->id_practica, false)
        ->assertSee('http://localhost:8080/mod/lti/return.php', false)
        ->assertSee('name="JWT"', false);
});
