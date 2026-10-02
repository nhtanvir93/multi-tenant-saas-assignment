<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Replaced = 'replaced';
    case Expired = 'expired';
}
