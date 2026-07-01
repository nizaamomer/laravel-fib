<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Events\Payments;

use Illuminate\Foundation\Events\Dispatchable;
use Nizaamomer\LaravelFib\Data\Payments\RefundData;

final class PaymentRefundRequested
{
    use Dispatchable;

    public function __construct(
        public readonly RefundData $refund,
        public readonly string $account,
    ) {}
}
