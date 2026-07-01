<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Events\Payments;

use Illuminate\Foundation\Events\Dispatchable;
use Nizaamomer\LaravelFib\Data\Payments\PaymentData;

final class PaymentCreated
{
    use Dispatchable;

    public function __construct(
        public readonly PaymentData $payment,
        public readonly string $account,
        public readonly float $amount,
        public readonly string $currency,
    ) {}
}
