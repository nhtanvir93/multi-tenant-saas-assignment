<?php

namespace App\Providers;

use App\Limits\CustomerLimitCheck;
use App\Limits\LimitCheck;
use App\Limits\LimitEnforcer;
use App\Limits\UserLimitCheck;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\User;
use App\Observers\CustomerObserver;
use App\Observers\SubscriptionObserver;
use App\Observers\UserObserver;
use App\Repositories\CachedCustomerRepository;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\EloquentCustomerRepository;
use App\Services\UsageService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn (): TenantContext => new TenantContext);

        $this->app->bind(UserLimitCheck::class);

        $this->app->tag(
            [
                UserLimitCheck::class,
                CustomerLimitCheck::class,
            ],
            'subscription.limits',
        );

        $this->app->bind(
            LimitEnforcer::class,
            function ($app): LimitEnforcer {
                /** @var iterable<LimitCheck> $checks */
                $checks = $app->tagged('subscription.limits');

                return new LimitEnforcer($checks);
            },
        );

        $this->app->bind(
            UsageService::class,
            function ($app): UsageService {
                /** @var iterable<LimitCheck> $checks */
                $checks = $app->tagged('subscription.limits');

                return new UsageService($checks);
            },
        );

        $this->app->bind(
            CustomerRepositoryInterface::class,
            EloquentCustomerRepository::class,
        );

        $this->app->bind(CustomerLimitCheck::class);

        $this->app->tag(
            [UserLimitCheck::class, CustomerLimitCheck::class],
            'subscription.limits',
        );

        $this->app->bind(
            CustomerRepositoryInterface::class,
            CachedCustomerRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        User::observe(UserObserver::class);
        Customer::observe(CustomerObserver::class);
        Subscription::observe(SubscriptionObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
