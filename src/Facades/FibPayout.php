<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Facades;

use Illuminate\Support\Facades\Facade;
use Nizaamomer\LaravelFib\Contracts\Payouts\FibPayoutServiceContract;
use Nizaamomer\LaravelFib\Data\Payouts\PayoutData;
use Nizaamomer\LaravelFib\Data\Payouts\PayoutStatusData;

/**
 * @method static PayoutData create(float $amount, string $targetAccountIban, ?string $description = null, string $currency = 'IQD', ?string $account = null)
 * @method static bool authorize(string $payoutId, ?string $account = null)
 * @method static PayoutStatusData details(string $payoutId, ?string $account = null)
 *
 * @see FibPayoutServiceContract
 */
class FibPayout extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FibPayoutServiceContract::class;
    }
}
