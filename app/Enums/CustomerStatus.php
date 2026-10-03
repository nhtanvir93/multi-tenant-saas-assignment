<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the lifecycle status of a customer.
 */
enum CustomerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Lead = 'lead';
}
