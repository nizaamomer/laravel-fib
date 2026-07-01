<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Data\Payments;

use Nizaamomer\LaravelFib\Enums\Payments\RefundStatus;

/**
 * The /payments/{id}/refund endpoint is not part of FIB's published API
 * docs — this shape was reverse-engineered from First Iraqi Bank's own
 * fib-laravel-payment-sdk source. Treat it as unstable until confirmed
 * against your sandbox account.
 */
final readonly class RefundData
{
    public function __construct(
        public string $paymentId,
        public RefundStatus $status,
        public ?string $traceId,
        /** @var array<int, string>|null */
        public ?array $errorCodes,
    ) {}

    public static function accepted(string $paymentId): self
    {
        return new self($paymentId, RefundStatus::Success, null, null);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function declined(string $paymentId, array $body): self
    {
        $errors = $body['errors'] ?? [];

        return new self(
            paymentId: $paymentId,
            status: RefundStatus::Failed,
            traceId: $body['traceId'] ?? null,
            errorCodes: is_array($errors) ? array_column($errors, 'code') : null,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status === RefundStatus::Success;
    }
}
