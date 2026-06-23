<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/jugar/{token}', function (string $token) {
    $scheme = config('metaverso.deeplink_scheme', 'tecnm-metaverso');
    return view('jugar', ['deeplink' => "{$scheme}://play?token={$token}"]);
});
