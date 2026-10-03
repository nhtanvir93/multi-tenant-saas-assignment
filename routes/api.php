<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Public routes
    |--------------------------------------------------------------------------
    */

    Route::get('health', HealthController::class);

    Route::get('/plans', [PlanController::class, 'index']);

    Route::prefix('auth')->group(function (): void {
        Route::middleware('throttle:auth')->group(function (): void {
            Route::post(
                'register-company',
                [AuthController::class, 'registerCompany']
            );

            Route::post(
                'login',
                [AuthController::class, 'login']
            );
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated routes
    |--------------------------------------------------------------------------
    |
    | SetTenantContext must run after Sanctum authentication so the
    | authenticated user's company becomes the active tenant.
    |
    */

    Route::middleware([
        'auth:sanctum',
        'throttle:api',
        SetTenantContext::class,
    ])->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Authentication
        |--------------------------------------------------------------------------
        */

        Route::post(
            'auth/logout',
            [AuthController::class, 'logout']
        );

        Route::get(
            'auth/me',
            [AuthController::class, 'me']
        );

        /*
        |--------------------------------------------------------------------------
        | Company
        |--------------------------------------------------------------------------
        */

        Route::get('/company', [CompanyController::class, 'show']);

        Route::put('/company', [CompanyController::class, 'update']);

        /*
        |--------------------------------------------------------------------------
        | Subscription
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

        Route::apiResource('users', UserController::class);

        /*
        |--------------------------------------------------------------------------
        | Customers
        |--------------------------------------------------------------------------
        */

        Route::apiResource('customers', CustomerController::class);

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', DashboardController::class);
    });
});
