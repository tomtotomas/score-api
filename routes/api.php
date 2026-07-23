<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScoreController;
use App\Http\Controllers\GameController;

Route::middleware('throttle:60,1')->group(function () {
    Route::post('/game/start', [GameController::class, 'start']);
    Route::post('/game/{gameId}/hit', [GameController::class, 'hit']);
    Route::post('/game/{gameId}/stand', [GameController::class, 'stand']);
    Route::get('/game/{gameId}/save-url', [GameController::class, 'requestSaveUrl']);

    Route::get('/scores', [ScoreController::class, 'index']);
    Route::post('/scores/{game_id}', [ScoreController::class, 'store'])
        ->middleware('signed')
        ->name('api.save-score');
});

