<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Events\Payouts;

use Illuminate\Foundation\Events\Dispatchable;
use Nizaamomer\LaravelFib\Data\Payouts\PayoutData;

final class PayoutCreated
{
    use Dispatchable;

    public function __construct(
        public readonly PayoutData $payout,
        public readonly string $account,
        public readonly float $amount,
        public readonly string $targetAccountIban,
        public readonly ?string $description,
        public readonly string $currency,
    ) {}
}
