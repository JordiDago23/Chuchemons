<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

// ─── RUTAS PÚBLICAS ──────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ─── RUTAS PROTEGIDAS (requieren JWT) ────────────────────
Route::middleware('auth:api')->group(function () {
    Route::get('/me',          [AuthController::class, 'me']);
    Route::post('/logout',     [AuthController::class, 'logout']);
    Route::put('/user/update', [UserController::class, 'update']);
    Route::delete('/user',     [UserController::class, 'delete']);
});