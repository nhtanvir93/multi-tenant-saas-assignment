<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case User = 'user';
}
