<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Company $company */
        $company = $this->resource;

        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'created_at' => $company->created_at?->toISOString(),
            'updated_at' => $company->updated_at?->toISOString(),
        ];
    }
}
