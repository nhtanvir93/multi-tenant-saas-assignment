<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Company $company */
        $company = $user->company;

        Gate::authorize('view', $company);

        return ApiResponse::success(
            new CompanyResource($company),
            'Company retrieved.',
        );
    }

    public function update(UpdateCompanyRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Company $company */
        $company = $user->company;

        Gate::authorize('update', $company);

        /** @var array{name: string} $data */
        $data = $request->validated();

        $company = $this->companyService->update($company, $data);

        return ApiResponse::success(
            new CompanyResource($company),
            'Company updated.',
        );
    }
}
