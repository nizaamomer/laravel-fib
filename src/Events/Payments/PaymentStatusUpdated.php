<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Events\Payments;

use Illuminate\Foundation\Events\Dispatchable;
use Nizaamomer\LaravelFib\Data\Payments\PaymentStatusData;

final class PaymentStatusUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly PaymentStatusData $status,
        public readonly string $account,
    ) {}
}
