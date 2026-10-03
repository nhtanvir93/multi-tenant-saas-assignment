<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates data required to create a tenant user.
 */
final class CreateUserRequest extends FormRequest
{
    /**
     * Authorization is handled by UserPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for user creation.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
            ],
            'role' => [
                'required',
                Rule::in([
                    Role::Admin->value,
                    Role::User->value,
                ]),
            ],
        ];
    }
}
