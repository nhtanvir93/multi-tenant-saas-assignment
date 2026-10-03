<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CustomerFilters;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent implementation of the customer repository.
 */
final class EloquentCustomerRepository implements CustomerRepositoryInterface
{
    /**
     * Retrieve a filtered and paginated customer list.
     *
     * @return LengthAwarePaginator<int, Customer>
     */
    public function paginate(CustomerFilters $filters): LengthAwarePaginator
    {
        /** @var Builder<Customer> $query */
        $query = Customer::query()
            ->select([
                'id',
                'company_id',
                'name',
                'email',
                'phone',
                'status',
                'notes',
                'created_at',
                'updated_at',
            ]);

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->search !== null && $filters->search !== '') {
            $search = mb_strtolower($filters->search);

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->whereRaw(
                        'LOWER(name) LIKE ?',
                        ["%{$search}%"],
                    )
                    ->orWhereRaw(
                        'LOWER(email) LIKE ?',
                        ["%{$search}%"],
                    );
            });
        }

        $this->applySort($query, $filters->sort);

        return $query->paginate(
            perPage: $filters->perPage,
            page: $filters->page,
        );
    }

    /**
     * Find a customer by primary key.
     */
    public function find(int $id): ?Customer
    {
        return Customer::query()->find($id);
    }

    /**
     * Determine whether a live customer already uses an email address.
     *
     * PostgreSQL performs the authoritative uniqueness check through
     * the partial functional unique index.
     */
    public function emailExists(
        string $email,
        ?int $ignoreId = null,
    ): bool {
        $query = Customer::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)]);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * Persist a customer.
     */
    public function save(Customer $customer): Customer
    {
        $customer->save();

        return $customer->refresh();
    }

    /**
     * Soft-delete a customer.
     */
    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    /**
     * Apply a whitelisted sort expression.
     *
     * @param  Builder<Customer>  $query
     */
    private function applySort(
        Builder $query,
        string $sort,
    ): void {
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $allowedColumns = [
            'name',
            'email',
            'status',
            'created_at',
        ];

        if (! in_array($column, $allowedColumns, true)) {
            $column = 'created_at';
            $descending = true;
        }

        $direction = $descending ? 'desc' : 'asc';

        $query
            ->orderBy($column, $direction)
            ->orderBy('id', $direction);
    }
}
