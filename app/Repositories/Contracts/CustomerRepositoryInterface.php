<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Data\CustomerFilters;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Defines persistence operations for tenant customers.
 */
interface CustomerRepositoryInterface
{
    /**
     * Retrieve a paginated customer list using the supplied filters.
     *
     * @return LengthAwarePaginator<int, Customer>
     */
    public function paginate(CustomerFilters $filters): LengthAwarePaginator;

    /**
     * Find a customer by its primary key.
     *
     * Tenant scoping is supplied by the Customer model.
     */
    public function find(int $id): ?Customer;

    /**
     * Determine whether a live customer already owns the given email.
     *
     * @param  int|null  $ignoreId  Customer ID to exclude during updates.
     */
    public function emailExists(
        string $email,
        ?int $ignoreId = null,
    ): bool;

    /**
     * Persist a customer.
     */
    public function save(Customer $customer): Customer;

    /**
     * Soft-delete a customer.
     */
    public function delete(Customer $customer): void;
}
