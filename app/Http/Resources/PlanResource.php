<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plan */
class PlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tier' => $this->tier,
            'price_cents' => $this->price_cents,
            // null = unlimited
            'limits' => [
                'users' => $this->max_users,
                'customers' => $this->max_customers,
            ],
            'features' => $this->features ?? [],
        ];
    }
}
