<?php

use App\Models\Materia;
use App\Models\TokenJuego;
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * El modo demo —simular la partida desde el navegador y devolver la
 * calificación— vivía en el interstitial del launch LTI. Al hacerse obligatoria
 * la reserva ese interstitial dejó de ser alcanzable, así que el modo demo se
 * mudó a /jugar/{token}, que es la única entrada al juego que queda.
 */
it('la pantalla de jugar trae el deeplink y el modo demo con el token en crudo', function () {
    Materia::create(['clave' => 'PRG', 'nombre' => 'Prog', 'creditos' => 5]);

    $respuesta = $this->get('/jugar/token-de-prueba');

    $respuesta->assertOk()
        ->assertSee('Modo demo')
        ->assertSee('/api/game/redeem', false)
        // El token en crudo es lo que el demo necesita para canjear sin el motor.
        ->assertSee('token-de-prueba', false);
});

it('el enlace de juego dispara el esquema propio configurado', function () {
    $esquema = config('metaverso.deeplink_scheme', 'tecnm-metaverso');

    $this->get('/jugar/abc123')
        ->assertOk()
        ->assertSee("{$esquema}://play?token=abc123", false);
});

it('no acepta tokens con caracteres fuera del alfabeto del magic link', function () {
    $this->get('/jugar/no%20valido')->assertNotFound();
});

it('el magic link real llega a la pantalla de jugar', function () {
    $e = reservarEscenario();
    $res = app(MagicLinkService::class)->generar($e['usuario']->id_usuario, $e['evento']->id_evento);

    expect(TokenJuego::count())->toBe(1);

    $this->get($res['url'])->assertOk()->assertSee('Modo demo');
});
