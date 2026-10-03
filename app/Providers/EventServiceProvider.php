<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\TenantResourceChanged;
use App\Listeners\InvalidateTenantCache;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Registers application event listeners.
 */
final class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        TenantResourceChanged::class => [
            InvalidateTenantCache::class,
        ],
    ];
}
