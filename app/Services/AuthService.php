<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class AuthService
{
    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        /** @var User|null $user */
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();

        if (
            $user === null ||
            $user->status !== UserStatus::Active ||
            ! Hash::check($password, $user->password)
        ) {
            throw new BusinessRuleException(
                'Credentials mismatched',
                errorCode: 'INVALID_CREDENTIALS',
                httpStatus: 401,
                details: []
            );
        }

        $token = $user->createToken('api');

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
