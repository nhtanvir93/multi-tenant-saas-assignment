<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\CustomerStatus;

/**
 * Immutable filters used when querying the customer list.
 */
final readonly class CustomerFilters
{
    /**
     * @param  string|null  $search  Search term applied to customer name/email.
     * @param  CustomerStatus|null  $status  Customer status filter.
     * @param  string  $sort  Whitelisted sort expression.
     * @param  int  $perPage  Number of records per page.
     * @param  int  $page  Requested page number.
     */
    public function __construct(
        public ?string $search = null,
        public ?CustomerStatus $status = null,
        public string $sort = '-created_at',
        public int $perPage = 20,
        public int $page = 1,
    ) {}
}
