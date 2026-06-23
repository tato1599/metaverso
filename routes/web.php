<?php

use Illuminate\Support\Facades\Route;

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
