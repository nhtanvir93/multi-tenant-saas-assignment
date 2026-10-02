<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Provides the readable subscription plan catalogue.
 */
final class PlanController extends Controller
{
    /**
     * Return all active subscription plans ordered by tier.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->active()
            ->orderedByTier()
            ->get();

        return ApiResponse::success(
            PlanResource::collection($plans),
            'Plans retrieved.',
        );
    }
}
