<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Data\Payouts;

use Carbon\CarbonImmutable;
use Nizaamomer\LaravelFib\Enums\Payouts\PayoutCurrency;
use Nizaamomer\LaravelFib\Enums\Payouts\PayoutStatus;

final readonly class PayoutStatusData
{
    public function __construct(
        public string $payoutId,
        public PayoutStatus $status,
        public string $targetAccountIban,
        public ?string $description,
        public float $amount,
        public PayoutCurrency $currency,
        public ?CarbonImmutable $authorizedAt,
        public ?CarbonImmutable $failedAt,
        public ?string $failureReason,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            payoutId: $data['payoutId'],
            status: PayoutStatus::from($data['status']),
            targetAccountIban: $data['targetAccountIban'],
            description: $data['description'] ?? null,
            amount: (float) ($data['amount']['amount'] ?? 0),
            currency: PayoutCurrency::from($data['amount']['currency'] ?? 'IQD'),
            authorizedAt: self::parseTimestamp($data['authorizedAt'] ?? null),
            failedAt: self::parseTimestamp($data['failedAt'] ?? null),
            failureReason: $data['failureReason'] ?? null,
        );
    }

    public function isAuthorized(): bool
    {
        return $this->status === PayoutStatus::Authorized;
    }

    public function isFailed(): bool
    {
        return $this->status === PayoutStatus::Failed;
    }

    private static function parseTimestamp(int|string|null $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return is_int($value) ? CarbonImmutable::createFromTimestamp($value) : CarbonImmutable::parse($value);
    }
}
