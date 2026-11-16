<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteLineController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/parts', [PartController::class, 'index']);
    Route::get('/parts/{part}', [PartController::class, 'show']);

    Route::post('/quotes', [QuoteController::class, 'store']);

    Route::scopeBindings()->group(function () {
        Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->can('view', 'quote');

        Route::middleware('can:update,quote')->group(function () {
            Route::post('/quotes/{quote}/submit', [QuoteController::class, 'submit']);
            Route::post('/quotes/{quote}/lines', [QuoteLineController::class, 'store']);
            Route::patch('/quotes/{quote}/lines/{line}', [QuoteLineController::class, 'update']);
            Route::delete('/quotes/{quote}/lines/{line}', [QuoteLineController::class, 'destroy']);
        });
    });
});
