<?php

declare(strict_types=1);

namespace App\Services;

use App\Limits\LimitEnforcer;
use App\Models\Company;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Handles customer business operations and transactions.
 */
final class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $repository,
        private readonly LimitEnforcer $limitEnforcer,
    ) {}

    /**
     * Create a customer after enforcing the subscription limit.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     phone?: string|null,
     *     status: string,
     *     notes?: string|null
     * } $data
     */
    public function create(Company $company, array $data): Customer
    {
        return DB::transaction(function () use ($company, $data): Customer {
            $this->limitEnforcer->ensureCanCreate(
                $company,
                'customers',
            );

            $customer = new Customer;

            $customer->fill([
                'name' => $data['name'],
                'email' => mb_strtolower(trim($data['email'])),
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]);

            return $this->repository->save($customer);
        });
    }

    /**
     * Update an existing customer.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     phone?: string|null,
     *     status: string,
     *     notes?: string|null
     * } $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->fill([
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->repository->save($customer);
    }

    /**
     * Soft-delete a customer.
     */
    public function delete(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $this->repository->delete($customer);
        });
    }
}
