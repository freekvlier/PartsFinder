<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PartController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/parts', [PartController::class, 'index']);
});
