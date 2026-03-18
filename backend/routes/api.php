<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChuchemonController;
use App\Http\Controllers\MochilaController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\AdminController;

// ─── RUTAS PÚBLICAS ──────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ─── RUTAS CHUCHEMONS (públicas) ───────────────────────
Route::get('/chuchemons', [ChuchemonController::class, 'index']);
Route::get('/chuchemons/element/{element}', [ChuchemonController::class, 'filterByElement']);
Route::get('/chuchemons/mida/{mida}',       [ChuchemonController::class, 'filterByMida']);
Route::get('/chuchemons/search/{query}',    [ChuchemonController::class, 'search']);
Route::get('/chuchemons/{id}',              [ChuchemonController::class, 'show']);

// ─── RUTAS ITEMS (públicas) ────────────────────────────
Route::get('/items',      [ItemController::class, 'index']);
Route::get('/items/{id}', [ItemController::class, 'show']);

// ─── RUTAS PROTEGIDAS (requieren JWT) ────────────────────
Route::middleware('auth:api')->group(function () {
    Route::get('/me',          [AuthController::class, 'me']);
    Route::post('/logout',     [AuthController::class, 'logout']);
    Route::put('/user/update', [UserController::class, 'update']);
    Route::delete('/user',     [UserController::class, 'delete']);

    // ─── USUARIO - CHUCHEMONS ──────────────────────────────
    Route::get('/user/chuchemons',          [ChuchemonController::class, 'getMyChuchemons']);
    Route::post('/user/chuchemons/{id}/capture', [ChuchemonController::class, 'capture']);
    Route::get('/user/team',                [ChuchemonController::class, 'getTeam']);
    Route::post('/user/team',               [ChuchemonController::class, 'saveTeam']);

    // ─── EVOLUCIÓN ──────────────────────────────────────────
    Route::post('/user/chuchemons/{id}/evolve',    [ChuchemonController::class, 'evolve']);
    Route::get('/user/chuchemons/{id}/evolution',  [ChuchemonController::class, 'getEvolutionInfo']);

    // ─── MOCHILA ───────────────────────────────────────────
    Route::get('/mochila',              [MochilaController::class, 'index']);
    Route::post('/mochila/add-xux',     [MochilaController::class, 'addXux']);
    Route::post('/mochila/add-item',    [MochilaController::class, 'addItem']);
    Route::put('/mochila/{id}',         [MochilaController::class, 'update']);
    Route::delete('/mochila/{id}',      [MochilaController::class, 'destroy']);

    // ─── ADMIN ────────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::get('/stats',                        [AdminController::class, 'stats']);
        Route::get('/users',                        [AdminController::class, 'listUsers']);
        Route::post('/users/{id}/add-xux',          [AdminController::class, 'addXuxToUser']);
        Route::post('/users/{id}/add-item',         [AdminController::class, 'addItemToUser']);
        Route::post('/users/{id}/add-chuchemon',    [AdminController::class, 'addRandomChuchemon']);

        // CRUD Xuxemons (admin)
        Route::post('/chuchemons',       [ChuchemonController::class, 'store']);
        Route::put('/chuchemons/{id}',   [ChuchemonController::class, 'update']);
        Route::delete('/chuchemons/{id}',[ChuchemonController::class, 'destroy']);

        // CRUD Items (admin)
        Route::post('/items',       [ItemController::class, 'store']);
        Route::put('/items/{id}',   [ItemController::class, 'update']);
        Route::delete('/items/{id}',[ItemController::class, 'destroy']);
    });
});