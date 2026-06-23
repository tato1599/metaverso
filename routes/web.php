<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Panel\PanelLoginController;

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

// Ruta temporal del dashboard (se reemplaza en Task 3)
Route::get('/panel', fn () => 'ok')->name('panel.dashboard')->middleware('panel');
