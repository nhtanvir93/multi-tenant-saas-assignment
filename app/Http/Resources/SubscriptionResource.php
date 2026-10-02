<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Subscription $subscription */
        $subscription = $this->resource;

        return [
            'id' => $subscription->id,
            'status' => $subscription->status->value,
            'starts_at' => $subscription->starts_at?->toISOString(),
            'ends_at' => $subscription->ends_at?->toISOString(),
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}
