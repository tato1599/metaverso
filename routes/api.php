<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameAuthController;
use App\Http\Controllers\Api\GameSessionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/game/redeem', [GameAuthController::class, 'redeem']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/game/me', [GameSessionController::class, 'me']);
    Route::post('/game/sessions', [GameSessionController::class, 'start']);
    Route::post('/game/sessions/{id}/complete', [GameSessionController::class, 'complete']);
});
