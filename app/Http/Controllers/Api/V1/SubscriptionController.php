<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\UsageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Handles subscription and subscription-usage HTTP endpoints.
 *
 * Business rules remain inside services; this controller only coordinates
 * request validation, authorization, service calls, and response resources.
 */
final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly UsageService $usageService,
    ) {}

    /**
     * Display the company's current subscription.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Subscription $subscription */
        $subscription = Subscription::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->with('plan')
            ->firstOrFail();

        Gate::authorize('view', $subscription);

        return ApiResponse::success(
            new SubscriptionResource($subscription),
            'Subscription retrieved.',
        );
    }

    /**
     * Upgrade the company's subscription.
     */
    public function update(
        UpdateSubscriptionRequest $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var Subscription $subscription */
        $subscription = Subscription::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->with('plan')
            ->firstOrFail();

        Gate::authorize('upgrade', $subscription);

        /** @var array{plan_id: int} $data */
        $data = $request->validated();

        $subscription = $this->subscriptionService->upgrade(
            $subscription,
            $data['plan_id'],
        );

        return ApiResponse::success(
            new SubscriptionResource($subscription),
            'Subscription upgraded successfully.',
        );
    }

    /**
     * Display current subscription usage.
     */
    public function usage(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Subscription $subscription */
        $subscription = Subscription::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->firstOrFail();

        Gate::authorize('viewUsage', $subscription);

        return ApiResponse::success(
            $this->usageService->forCompany($user->company),
            'Subscription usage retrieved.',
        );
    }
}
