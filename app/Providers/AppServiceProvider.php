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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureRateLimiters();
        $this->configureLazyLoadingProtection();

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

    /**
     * Configure named application rate limiters.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('auth', function (Request $request): Limit {
            $email = mb_strtolower(
                trim((string) $request->input('email')),
            );

            return Limit::perMinute(5)->by(
                sprintf(
                    '%s|%s',
                    $request->ip() ?? 'unknown',
                    $email,
                ),
            );
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user();

            if ($user instanceof User) {
                return Limit::perMinute(60)->by(
                    'user:'.$user->getAuthIdentifier(),
                );
            }

            return Limit::perMinute(60)->by(
                'ip:'.($request->ip() ?? 'unknown'),
            );
        });

        RateLimiter::for('exports', function (Request $request): Limit {
            $user = $request->user();

            if ($user instanceof User) {
                return Limit::perMinute(10)->by(
                    'user:'.$user->getAuthIdentifier(),
                );
            }

            return Limit::perMinute(10)->by(
                'ip:'.($request->ip() ?? 'unknown'),
            );
        });
    }

    /**
     * Prevent accidental lazy-loading during development and tests.
     *
     * Production keeps lazy loading enabled to avoid changing runtime
     * behaviour outside the query-review environment.
     */
    private function configureLazyLoadingProtection(): void
    {
        Model::preventLazyLoading(
            app()->runningUnitTests() || ! app()->isProduction(),
        );
    }
}
