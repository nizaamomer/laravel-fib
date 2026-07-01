<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib;

use Illuminate\Support\Facades\Event;
use Nizaamomer\LaravelFib\Console\Commands\SyncFibStatuses;
use Nizaamomer\LaravelFib\Contracts\FibAuthServiceContract;
use Nizaamomer\LaravelFib\Contracts\Payments\FibPaymentServiceContract;
use Nizaamomer\LaravelFib\Contracts\Payouts\FibPayoutServiceContract;
use Nizaamomer\LaravelFib\Events\Payments\PaymentCreated;
use Nizaamomer\LaravelFib\Events\Payments\PaymentRefundRequested;
use Nizaamomer\LaravelFib\Events\Payments\PaymentStatusUpdated;
use Nizaamomer\LaravelFib\Events\Payouts\PayoutCreated;
use Nizaamomer\LaravelFib\Events\Payouts\PayoutStatusUpdated;
use Nizaamomer\LaravelFib\Listeners\PersistPaymentListener;
use Nizaamomer\LaravelFib\Listeners\PersistPayoutListener;
use Nizaamomer\LaravelFib\Services\FibAuthService;
use Nizaamomer\LaravelFib\Services\Payments\FibPaymentService;
use Nizaamomer\LaravelFib\Services\Payouts\FibPayoutService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FibServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('fib')
            ->hasConfigFile('fib')
            ->hasMigrations('create_fib_payments_table', 'create_fib_payouts_table', 'create_fib_refunds_table')
            ->runsMigrations()
            ->hasCommand(SyncFibStatuses::class);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(FibAuthServiceContract::class, FibAuthService::class);
        $this->app->singleton(FibPaymentServiceContract::class, FibPaymentService::class);
        $this->app->singleton(FibPayoutServiceContract::class, FibPayoutService::class);
    }

    public function packageBooted(): void
    {
        Event::listen(PaymentCreated::class, [PersistPaymentListener::class, 'onCreated']);
        Event::listen(PaymentStatusUpdated::class, [PersistPaymentListener::class, 'onStatusUpdated']);
        Event::listen(PaymentRefundRequested::class, [PersistPaymentListener::class, 'onRefundRequested']);
        Event::listen(PayoutCreated::class, [PersistPayoutListener::class, 'onCreated']);
        Event::listen(PayoutStatusUpdated::class, [PersistPayoutListener::class, 'onStatusUpdated']);
    }
}
