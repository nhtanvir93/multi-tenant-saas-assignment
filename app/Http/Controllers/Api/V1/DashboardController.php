<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles tenant dashboard API requests.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service,
    ) {}

    /**
     * Return dashboard analytics for the authenticated tenant.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        return ApiResponse::success(
            $this->service->forUser($user),
            'Dashboard retrieved successfully.',
        );
    }
}
