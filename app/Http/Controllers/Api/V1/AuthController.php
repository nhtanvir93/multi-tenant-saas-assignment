<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\CompanyRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly CompanyRegistrationService $registrationService,
    ) {}

    public function registerCompany(
        RegisterCompanyRequest $request
    ): JsonResponse {
        $result = $this->registrationService->register(
            $request->validated()
        );

        $token = $result['user']->createToken('api');

        return response()->json([
            'success' => true,
            'message' => 'Company registered successfully.',
            'data' => [
                'company' => new CompanyResource($result['company']),
                'user' => new UserResource($result['user']),
                'subscription' => new SubscriptionResource(
                    $result['subscription']->load('plan')
                ),
                'token' => $token->plainTextToken,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
            'data' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user.',
            'data' => new UserResource(
                $request->user()->load('company')
            ),
        ]);
    }
}
