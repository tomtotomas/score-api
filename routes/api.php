<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScoreController;


Route::get('/start-game', [ScoreController::class, 'startGame']);
Route::get('/scores', [ScoreController::class, 'index']);
Route::post('/scores/{game_id}', [ScoreController::class, 'store'])->middleware('signed')->name('api.save-score');
Route::delete('/scores/{score}', [ScoreController::class, 'destroy']);