<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates editable tenant-user fields.
 *
 * Business restrictions such as owner protection and self-action
 * protection are enforced by the policy/service layer.
 */
final class UpdateUserRequest extends FormRequest
{
    /**
     * Authorization is handled by UserPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for updating a user.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var string|null $userId */
        $userId = $this->route('user') instanceof User
            ? (string) $this->route('user')->id
            : null;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => [
                'sometimes',
                'required',
                'string',
                'min:8',
            ],
            'role' => [
                'sometimes',
                'required',
                Rule::enum(Role::class),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::enum(UserStatus::class),
            ],
        ];
    }
}
