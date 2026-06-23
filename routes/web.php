<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Panel\PanelLoginController;
use App\Http\Controllers\Panel\PanelController;
use App\Http\Controllers\Panel\PanelLinkController;
use App\Http\Controllers\Panel\PanelResultadoController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/jugar/{token}', function (string $token) {
    $scheme = config('metaverso.deeplink_scheme', 'tecnm-metaverso');
    return view('jugar', ['deeplink' => "{$scheme}://play?token={$token}"]);
})->where('token', '[A-Za-z0-9_\-]+');

Route::get('/demo', fn () => view('demo', [
    'apiKey'  => config('metaverso.links_api_key'),
    'baseUrl' => url('/'),
]));

Route::get('/panel/acceso/{usuario}', [PanelLoginController::class, 'acceso'])
    ->name('panel.acceso')->middleware('signed');
Route::post('/panel/salir', [PanelLoginController::class, 'salir'])->name('panel.salir');
Route::view('/panel/acceso-invalido', 'panel.acceso-invalido')->name('panel.acceso.invalido');

Route::middleware('panel')->group(function () {
    Route::get('/panel', [PanelController::class, 'dashboard'])->name('panel.dashboard');
    Route::get('/panel/grupos/{grupo}', [PanelController::class, 'show'])->name('panel.grupos.show');

    // Task 5: magic links (CSV se genera del lado del cliente desde la tabla renderizada)
    Route::post('/panel/grupos/{grupo}/eventos/{evento}/links', [PanelLinkController::class, 'generar'])->name('panel.grupos.eventos.links');

    Route::get('/panel/grupos/{grupo}/resultados', [PanelResultadoController::class, 'index'])->name('panel.grupos.resultados');
    Route::get('/panel/sesiones/{sesion}', [PanelResultadoController::class, 'sesion'])->name('panel.sesiones.show');
});
