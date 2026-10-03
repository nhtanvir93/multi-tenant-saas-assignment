<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Data\CustomerFilters;
use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Company;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\CustomerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Handles customer HTTP endpoints.
 */
final class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CustomerRepositoryInterface $repository,
    ) {}

    /**
     * List customers with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                Rule::enum(CustomerStatus::class),
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'name',
                    '-name',
                    'email',
                    '-email',
                    'status',
                    '-status',
                    'created_at',
                    '-created_at',
                ]),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = new CustomerFilters(
            search: $request->input('search'),
            status: $request->filled('status')
                ? CustomerStatus::from($request->string('status')->toString())
                : null,
            sort: $request->input('sort', 'created_at'),
            perPage: (int) $request->input('per_page', 15),
            page: (int) $request->input('page', 1),
        );

        $customers = $this->repository->paginate($filters);

        return ApiResponse::success(
            CustomerResource::collection($customers),
            'Customers retrieved successfully.',
            200,
            [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        );
    }

    /**
     * Create a customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        /** @var Company $company */
        $company = $request->user()->company;

        $data = $request->validated();

        /** @var array{
         *     name: string,
         *     email: string,
         *     phone?: string|null,
         *     status: string,
         *     notes?: string|null
         * } $data
         */
        $customer = $this->customerService->create(
            $company,
            $data,
        );

        return ApiResponse::created(
            new CustomerResource($customer),
            'Customer created successfully.',
        );
    }

    /**
     * Show a customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        return ApiResponse::success(
            new CustomerResource($customer),
            'Customer retrieved successfully.',
        );
    }

    /**
     * Update a customer.
     */
    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
    ): JsonResponse {
        Gate::authorize('update', $customer);

        $data = $request->validated();

        /** @var array{
         *     name: string,
         *     email: string,
         *     phone?: string|null,
         *     status: string,
         *     notes?: string|null
         * } $data
         */
        $customer = $this->customerService->update(
            $customer,
            $data,
        );

        return ApiResponse::success(
            new CustomerResource($customer),
            'Customer updated successfully.',
        );
    }

    /**
     * Soft-delete a customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        Gate::authorize('delete', $customer);

        $this->customerService->delete($customer);

        return ApiResponse::success(
            null,
            'Customer deleted successfully.',
        );
    }
}
