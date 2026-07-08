<?php

use App\Http\Controllers\Admin\CarreraController;
use App\Http\Controllers\Admin\CicloController;
use App\Http\Controllers\Admin\EspacioController;
use App\Http\Controllers\Admin\GrupoController;
use App\Http\Controllers\Admin\InscripcionController;
use App\Http\Controllers\Admin\MateriaController;
use App\Http\Controllers\Admin\PracticaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Lti\JwksController;
use App\Http\Controllers\Lti\LtiDeepLinkController;
use App\Http\Controllers\Lti\LtiLaunchController;
use App\Http\Controllers\Lti\LtiLoginController;
use App\Http\Controllers\Mi\CalendarioController;
use App\Http\Controllers\Mi\JugarController;
use App\Http\Controllers\Mi\ReservaController;
use App\Http\Controllers\Panel\AgendaController;
use App\Http\Controllers\Panel\PanelController;
use App\Http\Controllers\Panel\PanelLinkController;
use App\Http\Controllers\Panel\PanelLoginController;
use App\Http\Controllers\Panel\PanelResultadoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'alumno'])->group(function () {
    Route::get('/mi/calendario', [CalendarioController::class, 'index'])->name('mi.calendario');
    Route::post('/mi/reservas', [ReservaController::class, 'store'])
        ->middleware('throttle:10,1,reservas')->name('mi.reservas.store');
    Route::delete('/mi/reservas/{reserva}', [ReservaController::class, 'destroy'])
        ->name('mi.reservas.destroy');
    Route::post('/mi/eventos/{evento}/jugar', JugarController::class)
        ->middleware('throttle:10,1,jugar')->name('mi.eventos.jugar');
});

Route::get('/lti/jwks', [JwksController::class, 'index'])->name('lti.jwks');
Route::match(['get', 'post'], '/lti/login', [LtiLoginController::class, 'login'])->name('lti.login');
Route::post('/lti/launch', [LtiLaunchController::class, 'launch'])->name('lti.launch');
Route::get('/lti/deeplink', [LtiDeepLinkController::class, 'seleccionar'])->name('lti.deeplink');
Route::post('/lti/deeplink', [LtiDeepLinkController::class, 'responder'])->name('lti.deeplink.responder');

Route::get('/jugar/{token}', function (string $token) {
    $scheme = config('metaverso.deeplink_scheme', 'tecnm-metaverso');

    return view('jugar', ['deeplink' => "{$scheme}://play?token={$token}"]);
})->where('token', '[A-Za-z0-9_\-]+');

Route::get('/demo', function () {
    // Exponía LINKS_API_KEY en producción; la clave solo se prellena en local.
    abort_unless(app()->environment(['local', 'testing']), 404);

    return view('demo', [
        'apiKey' => app()->environment('local') ? config('metaverso.links_api_key') : null,
        'baseUrl' => url('/'),
    ]);
});

Route::middleware(['auth', 'admin'])->prefix('/admin')->name('admin.')->group(function () {
    $recursos = [
        'carreras' => [CarreraController::class, 'carrera'],
        'materias' => [MateriaController::class, 'materia'],
        'practicas' => [PracticaController::class, 'practica'],
        'ciclos' => [CicloController::class, 'ciclo'],
        'espacios' => [EspacioController::class, 'espacio'],
        'grupos' => [GrupoController::class, 'grupo'],
        'usuarios' => [UsuarioController::class, 'usuario'],
    ];
    foreach ($recursos as $uri => [$controlador, $parametro]) {
        Route::get("/{$uri}", [$controlador, 'index'])->name("{$uri}.index");
        Route::post("/{$uri}", [$controlador, 'store'])->name("{$uri}.store");
        Route::put("/{$uri}/{{$parametro}}", [$controlador, 'update'])->name("{$uri}.update");
        Route::delete("/{$uri}/{{$parametro}}", [$controlador, 'destroy'])->name("{$uri}.destroy");
    }
    Route::post('/grupos/{grupo}/inscripciones', [InscripcionController::class, 'store'])->name('grupos.inscripciones.store');
    Route::delete('/grupos/{grupo}/inscripciones/{inscripcion}', [InscripcionController::class, 'destroy'])->name('grupos.inscripciones.destroy');
});

Route::get('/panel/acceso/{usuario}', [PanelLoginController::class, 'acceso'])
    ->name('panel.acceso')->middleware('signed');
Route::post('/panel/salir', [PanelLoginController::class, 'salir'])->name('panel.salir');
Route::view('/panel/acceso-invalido', 'panel.acceso-invalido')->name('panel.acceso.invalido');

Route::middleware(['auth', 'panel'])->group(function () {
    Route::get('/panel', [PanelController::class, 'dashboard'])->name('panel.dashboard');

    Route::get('/panel/agenda', [AgendaController::class, 'index'])->name('panel.agenda');
    Route::post('/panel/eventos', [AgendaController::class, 'store'])->name('panel.eventos.store');
    Route::get('/panel/eventos/{evento}', [AgendaController::class, 'show'])->name('panel.eventos.show');
    Route::put('/panel/eventos/{evento}', [AgendaController::class, 'update'])->name('panel.eventos.update');
    Route::delete('/panel/eventos/{evento}', [AgendaController::class, 'destroy'])->name('panel.eventos.destroy');
    Route::get('/panel/grupos/{grupo}', [PanelController::class, 'show'])->name('panel.grupos.show');

    // Task 5: magic links (CSV se genera del lado del cliente desde la tabla renderizada)
    Route::post('/panel/grupos/{grupo}/eventos/{evento}/links', [PanelLinkController::class, 'generar'])->name('panel.grupos.eventos.links');

    Route::get('/panel/grupos/{grupo}/resultados', [PanelResultadoController::class, 'index'])->name('panel.grupos.resultados');
    Route::get('/panel/sesiones/{sesion}', [PanelResultadoController::class, 'sesion'])->name('panel.sesiones.show');
});
