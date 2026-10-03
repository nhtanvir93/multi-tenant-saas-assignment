<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates customer creation input.
 */
final class StoreCustomerRequest extends FormRequest
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
        $companyId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:150'],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->whereNull('deleted_at')),
            ],

            'phone' => ['nullable', 'string', 'max:30'],

            'status' => [
                'required',
                Rule::enum(CustomerStatus::class),
            ],

            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Normalize email input before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim($this->string('email')->toString())),
        ]);
    }
}
