<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameAuthController;
use App\Http\Controllers\Api\GameSessionController;
use App\Http\Controllers\Api\LinkController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/game/redeem', [GameAuthController::class, 'redeem']);

// MVP: sin auth (uso interno/maestro); proteger con teacher auth en fase 2
Route::post('/links', [LinkController::class, 'store']);

Route::middleware(['auth:sanctum', 'abilities:game'])->group(function () {
    Route::get('/game/me', [GameSessionController::class, 'me']);
    Route::post('/game/sessions', [GameSessionController::class, 'start']);
    Route::post('/game/sessions/{id}/complete', [GameSessionController::class, 'complete']);
});
