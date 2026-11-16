<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

// Rotas Públicas de Autenticação com Rate Limiting (Escopo 2.1)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Rotas Protegidas via Laravel Sanctum (Escopo 2.1, 2.2, 2.3 e 2.4)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('wallet')->group(function () {
        Route::get('/dashboard', [WalletController::class, 'dashboard']);
        Route::post('/deposit', [WalletController::class, 'deposit']);
        Route::post('/withdraw', [WalletController::class, 'withdraw']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
    });
});
