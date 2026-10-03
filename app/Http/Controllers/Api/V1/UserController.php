<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Limits\LimitEnforcer;
use App\Models\Company;
use App\Models\User;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Handles tenant user-management endpoints.
 *
 * Controllers only coordinate HTTP concerns. Business rules are delegated
 * to UserPolicy, UserService and LimitEnforcer.
 */
final class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly LimitEnforcer $limitEnforcer,
    ) {}

    /**
     * List users belonging to the authenticated user's company.
     *
     * Supports role/search filters and offset pagination.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::authorize('viewAny', User::class);

        $query = User::query()
            ->where('company_id', $actor->company_id)
            ->select([
                'id',
                'company_id',
                'name',
                'email',
                'role',
                'status',
                'created_at',
                'updated_at',
            ]);

        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'ILIKE', '%'.$search.'%')
                    ->orWhere('email', 'ILIKE', '%'.$search.'%');
            });
        }

        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $users = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::success(
            UserResource::collection($users->items()),
            'Users retrieved.',
            meta: [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        );
    }

    /**
     * Show a tenant user.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($actor->company_id !== $user->company_id) {
            abort(404);
        }

        Gate::authorize('view', $user);

        return ApiResponse::success(
            new UserResource($user),
            'User retrieved.',
        );
    }

    /**
     * Create a user within the authenticated user's company.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::authorize('create', User::class);

        /** @var array{
         *     name: string,
         *     email: string,
         *     password: string,
         *     role: string
         * } $validated
         */
        $validated = $request->validated();

        $role = Role::from($validated['role']);

        /** @var array{
         *     name: string,
         *     email: string,
         *     password: string,
         *     role: Role
         * } $data
         */
        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $role,
        ];

        if (
            $actor->role === Role::Admin
            && $role !== Role::User
        ) {
            throw new BusinessRuleException(
                'An admin can only create users.',
                errorCode: 'FORBIDDEN',
                httpStatus: 403,
            );
        }

        $user = $this->userService->create(
            $actor->company,
            $data,
            $this->limitEnforcer,
        );

        return ApiResponse::created(
            new UserResource($user),
            'User created successfully.',
        );
    }

    /**
     * Update a tenant user.
     */
    public function update(
        UpdateUserRequest $request,
        User $user,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        if ($actor->company_id !== $user->company_id) {
            abort(404);
        }

        Gate::authorize('update', $user);

        /** @var array{
         *     name?: string,
         *     email?: string,
         *     password?: string,
         *     role?: Role,
         *     status?: UserStatus
         * } $data
         */
        $data = $request->validated();

        if (array_key_exists('role', $data)) {
            $this->userService->ensureNotSelfAction(
                $actor,
                $user,
                'change your role',
            );
        }

        if ($actor->role === Role::Admin) {
            if (
                isset($data['role']) &&
                $data['role'] !== Role::User
            ) {
                abort(403);
            }

            if (
                isset($data['status']) &&
                $user->role !== Role::User
            ) {
                abort(403);
            }
        }

        if (
            $user->role === Role::Owner &&
            (
                isset($data['role']) ||
                isset($data['status'])
            )
        ) {
            $this->userService->ensureOwnerCanBeChanged(
                $user,
                'modified',
            );
        }

        $updatedUser = $this->userService->update(
            $user,
            $data,
        );

        return ApiResponse::success(
            new UserResource($updatedUser),
            'User updated successfully.',
        );
    }

    /**
     * Delete a tenant user permanently.
     */
    public function destroy(
        Request $request,
        User $user,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        if ($actor->company_id !== $user->company_id) {
            abort(404);
        }

        Gate::authorize('delete', $user);

        $this->userService->ensureNotSelfAction(
            $actor,
            $user,
            'delete',
        );

        $this->userService->ensureOwnerCanBeChanged(
            $user,
            'deleted',
        );

        $this->userService->delete($user);

        return ApiResponse::success(
            null,
            'User deleted successfully.',
        );
    }
}
