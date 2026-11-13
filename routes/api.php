<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ---- public: kiosk, patient phone, display board
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('services', [ServiceController::class, 'index']);
    Route::post('tickets', [TicketController::class, 'store'])->middleware('throttle:30,1');
    Route::get('tickets/{ticket}', [TicketController::class, 'show']);
    Route::get('services/{service}/board', [BoardController::class, 'show']);

    // ---- staff
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('services/{service}/call-next', [TicketController::class, 'callNext']);
        Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus']);

        // ---- admin
        Route::middleware(EnsureAdmin::class)->group(function () {
            Route::post('services', [ServiceController::class, 'store']);
            Route::patch('services/{service}', [ServiceController::class, 'update']);
            Route::get('stats/today', [StatsController::class, 'today']);
        });
    });
});
