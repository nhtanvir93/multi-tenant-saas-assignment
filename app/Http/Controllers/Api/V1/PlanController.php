<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Services\PlanService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function __construct(private readonly PlanService $plans) {}

    /** Public: the plan catalogue is global reference data (needed before registration). */
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            PlanResource::collection($this->plans->activePlans())->resolve(),
            'Plans retrieved.'
        );
    }
}
