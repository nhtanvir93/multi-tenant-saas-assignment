<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|ValidationRule|Password>>
     */
    public function rules(): array
    {
        return [
            'company_name' => [
                'required',
                'string',
                'max:150',
            ],

            'company_slug' => [
                'required',
                'string',
                'max:150',
                'alpha_dash',
                'unique:companies,slug',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_slug' => strtolower((string) $this->input('company_slug')),
            'email' => strtolower((string) $this->input('email')),
        ]);
    }

    /**
     * @return array{
     *     company_name: string,
     *     company_slug: string,
     *     name: string,
     *     email: string,
     *     password: string
     * }
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{
         *     company_name: string,
         *     company_slug: string,
         *     name: string,
         *     email: string,
         *     password: string
         * } $validated
         */
        $validated = parent::validated($key, $default);

        return $validated;
    }
}
