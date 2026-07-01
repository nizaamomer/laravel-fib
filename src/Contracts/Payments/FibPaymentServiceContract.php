<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Contracts\Payments;

use Nizaamomer\LaravelFib\Data\Payments\PaymentData;
use Nizaamomer\LaravelFib\Data\Payments\PaymentStatusData;
use Nizaamomer\LaravelFib\Data\Payments\RefundData;

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

    /**
     * Requests a refund for a paid payment.
     *
     * This endpoint is not documented in FIB's public API reference — it
     * was reverse-engineered from First Iraqi Bank's own
     * fib-laravel-payment-sdk source. Verify against your sandbox account
     * before relying on it in production.
     */
    public function refund(string $paymentId, ?string $account = null): RefundData;
}
