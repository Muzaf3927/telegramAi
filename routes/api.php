<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);

// Fal.ai InfiniTalk API (требует auth)
Route::prefix('fal-ai')->group(function () {
    Route::prefix('video')->group(function () {
        Route::post('/generate', [App\Http\Controllers\FalAiController::class, 'generateVideo']);
        Route::get('/check-status/{request_uuid}', [App\Http\Controllers\FalAiController::class, 'checkStatus']);
    });
    Route::prefix('prompt')->group(function () {
        Route::post('/generate', [App\Http\Controllers\FalAiController::class, 'generatePrompt']);
        Route::get('/check-status/{request_uuid}', [App\Http\Controllers\FalAiController::class, 'checkPromptStatus']);
    });
    Route::prefix('turbo')->group(function () {
        Route::post('/generate', [App\Http\Controllers\FalAiController::class, 'generateVideoTurbo']);
        Route::get('/check-status/{request_uuid}', [App\Http\Controllers\FalAiController::class, 'checkTurboStatus']);
    });
    Route::prefix('fast')->group(function () {
        Route::post('/generate', [App\Http\Controllers\FalAiController::class, 'generateVideoVeoFast']);
        Route::get('/check-status/{request_uuid}', [App\Http\Controllers\FalAiController::class, 'checkVeoFastStatus']);
    });
});

