<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CustomerFilters;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Cached decorator for customer repository operations.
 *
 * Reads are cached while writes are delegated to the underlying repository.
 * Write invalidation is handled by the customer cache invalidation flow.
 */
final class CachedCustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        private readonly EloquentCustomerRepository $repository,
        private readonly CacheService $cacheService,
    ) {}

    /**
     * Fetch a paginated customer list through cache-aside.
     *
     * @return LengthAwarePaginator<int, Customer>
     */
    public function paginate(CustomerFilters $filters): LengthAwarePaginator
    {
        $queryHash = md5(
            json_encode(
                [
                    'search' => $filters->search,
                    'status' => $filters->status?->value,
                    'sort' => $filters->sort,
                    'per_page' => $filters->perPage,
                    'page' => $filters->page,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        return $this->cacheService->customers(
            $queryHash,
            fn (): LengthAwarePaginator => $this->repository->paginate(
                $filters,
            ),
        );
    }

    /**
     * Find a customer by primary key.
     */
    public function find(int $id): ?Customer
    {
        return $this->repository->find($id);
    }

    /**
     * Determine whether a live customer already owns the given email.
     *
     * Email-existence checks are intentionally not cached because they are
     * part of write validation and must observe current database state.
     */
    public function emailExists(
        string $email,
        ?int $ignoreId = null,
    ): bool {
        return $this->repository->emailExists(
            $email,
            $ignoreId,
        );
    }

    /**
     * Persist a customer.
     */
    public function save(Customer $customer): Customer
    {
        return $this->repository->save($customer);
    }

    /**
     * Soft-delete a customer.
     */
    public function delete(Customer $customer): void
    {
        $this->repository->delete($customer);
    }
}
