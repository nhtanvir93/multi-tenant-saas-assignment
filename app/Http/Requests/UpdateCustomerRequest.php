<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates customer update input.
 */
final class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may submit the request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');

        /** @var Customer $customer */
        $companyId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:150'],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where(
                        fn ($query) => $query
                            ->where('company_id', $companyId)
                            ->whereNull('deleted_at')
                    )
                    ->ignore($customer->id),
            ],

            'phone' => ['nullable', 'string', 'max:30'],

            'status' => [
                'required',
                Rule::enum(CustomerStatus::class),
            ],

            'notes' => ['nullable', 'string'],
        ];
    }
}
