<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChuchemonController;
use App\Http\Controllers\MochilaController;
use App\Http\Controllers\AdminController;

// ─── RUTAS PÚBLICAS ──────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ─── RUTAS CHUCHEMONS (públicas) ───────────────────────
Route::get('/chuchemons', [ChuchemonController::class, 'index']);
Route::get('/chuchemons/{id}', [ChuchemonController::class, 'show']);
Route::get('/chuchemons/element/{element}', [ChuchemonController::class, 'filterByElement']);
Route::get('/chuchemons/search/{query}', [ChuchemonController::class, 'search']);

// ─── RUTAS PROTEGIDAS (requieren JWT) ────────────────────
Route::middleware('auth:api')->group(function () {
    Route::get('/me',          [AuthController::class, 'me']);
    Route::post('/logout',     [AuthController::class, 'logout']);
    Route::put('/user/update', [UserController::class, 'update']);
    Route::delete('/user',     [UserController::class, 'delete']);

    // ─── MOCHILA ───────────────────────────────────────────
    Route::get('/mochila',          [MochilaController::class, 'index']);
    Route::post('/mochila/add-xux', [MochilaController::class, 'addXux']);

    // ─── ADMIN ─────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::get('/stats',                        [AdminController::class, 'stats']);
        Route::get('/users',                        [AdminController::class, 'listUsers']);
        Route::post('/users/{id}/add-xux',          [AdminController::class, 'addXuxToUser']);
        Route::post('/users/{id}/add-chuchemon',    [AdminController::class, 'addRandomChuchemon']);
    });
});