<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChuchemonController;
use App\Http\Controllers\MochilaController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\InfectionController;
use App\Http\Controllers\LevelingController;
use App\Http\Controllers\DailyRewardController;
use App\Http\Controllers\FriendshipController;

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

    // ─── AMIGOS ─────────────────────────────────────────────
    Route::get('/friends', [FriendshipController::class, 'index']);
    Route::get('/friends/search', [FriendshipController::class, 'search']);
    Route::post('/friends/request', [FriendshipController::class, 'sendRequest']);
    Route::post('/friends/requests/{friendship}/accept', [FriendshipController::class, 'acceptRequest']);
    Route::delete('/friends/requests/{friendship}', [FriendshipController::class, 'destroyRequest']);
    Route::delete('/friends/{friend}', [FriendshipController::class, 'removeFriend']);

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

    // ─── LEVELING ──────────────────────────────────────────
    Route::get('/level/chuchemons',     [LevelingController::class, 'getAllChuchemonsWithLevels']);
    Route::get('/level/chuchemon/{id}', [LevelingController::class, 'getChuchemonLevel']);
    Route::post('/level/chuchemon/{id}/add-experience/{amount}', [LevelingController::class, 'addExperience']);

    // ─── HP: curar i gastar Xuxes ───────────────────────────
    Route::post('/user/chuchemons/{id}/heal',    [LevelingController::class, 'healChuchemon']);
    Route::post('/user/chuchemons/{id}/use-xux', [LevelingController::class, 'useXuxForExperience']);

    // ─── INFECTIONS & MALALTIES ─────────────────────────────
    Route::get('/infections',           [InfectionController::class, 'getActiveInfections']);
    Route::post('/infections/infect/{chuchemonId}/{malaltiaId}', [InfectionController::class, 'infectChuchemon']);
    Route::post('/infections/cure/{infectionId}/{vaccineId}',    [InfectionController::class, 'cureInfection']);
    Route::get('/malalties',            [InfectionController::class, 'getMalalties']);
    Route::get('/vaccines',             [InfectionController::class, 'getVaccines']);

    // ─── DAILY REWARDS ──────────────────────────────────────
    Route::get('/daily-rewards',        [DailyRewardController::class, 'getDailyRewards']);
    Route::post('/daily-rewards/xux',   [DailyRewardController::class, 'claimXuxReward']);
    Route::post('/daily-rewards/chuchemon', [DailyRewardController::class, 'claimChuchemonReward']);

    // ─── ADMIN ────────────────────────────────────────────────────────
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/stats',                        [AdminController::class, 'stats']);
        Route::get('/users',                        [AdminController::class, 'listUsers']);
        Route::get('/settings',                     [AdminController::class, 'settings']);
        Route::put('/settings/config',              [AdminController::class, 'updateEvolutionConfig']);
        Route::put('/settings/infection-rate',      [AdminController::class, 'updateInfectionRate']);
        Route::put('/settings/schedules/xux',       [AdminController::class, 'updateDailyXuxSchedule']);
        Route::put('/settings/schedules/chuchemon', [AdminController::class, 'updateDailyChuchemonSchedule']);
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