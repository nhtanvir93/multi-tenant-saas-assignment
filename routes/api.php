<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

// Final URLs: /api/v1/...
Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class);
});
