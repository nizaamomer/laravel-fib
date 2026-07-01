<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Contracts\Payments;

use Nizaamomer\LaravelFib\Data\Payments\PaymentData;
use Nizaamomer\LaravelFib\Data\Payments\PaymentStatusData;

interface FibPaymentServiceContract
{
    public function create(
        float $amount,
        ?string $description = null,
        ?string $callbackUrl = null,
        ?string $account = null,
    ): PaymentData;

    public function status(string $paymentId, ?string $account = null): PaymentStatusData;

    public function cancel(string $paymentId, ?string $account = null): bool;
}
