<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class);
    Route::get('/plans', [PlanController::class, 'index']);

    Route::prefix('auth')->group(function (): void {
        Route::post(
            'register-company',
            [AuthController::class, 'registerCompany']
        );

        Route::post(
            'login',
            [AuthController::class, 'login']
        );
    });

    Route::middleware('auth:sanctum')->prefix('auth')->group(function (): void {
        Route::post(
            'logout',
            [AuthController::class, 'logout']
        );

        Route::get(
            'me',
            [AuthController::class, 'me']
        );
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/company', [CompanyController::class, 'show']);
        Route::put('/company', [CompanyController::class, 'update']);

        Route::get('/subscription', [
            SubscriptionController::class,
            'show',
        ]);

        Route::put('/subscription', [
            SubscriptionController::class,
            'update',
        ]);

        Route::get('/subscription/usage', [
            SubscriptionController::class,
            'usage',
        ]);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});
