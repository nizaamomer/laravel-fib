<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Data\Payments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Nizaamomer\LaravelFib\Enums\Payments\DecliningReason;
use Nizaamomer\LaravelFib\Enums\Payments\PaymentStatus;

final readonly class PaymentStatusData
{
    public function __construct(
        public string $paymentId,
        public PaymentStatus $status,
        public CarbonImmutable $validUntil,
        public ?CarbonImmutable $paidAt,
        public ?float $amount,
        public ?string $currency,
        public ?DecliningReason $decliningReason,
        public ?CarbonImmutable $declinedAt,
        public ?string $paidByName,
        public ?string $paidByIban,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            paymentId: $data['paymentId'],
            status: PaymentStatus::from($data['status']),
            validUntil: CarbonImmutable::parse($data['validUntil']),
            paidAt: isset($data['paidAt']) ? CarbonImmutable::parse($data['paidAt']) : null,
            amount: isset($data['amount']['amount']) ? (float) $data['amount']['amount'] : null,
            currency: $data['amount']['currency'] ?? null,
            decliningReason: isset($data['decliningReason']) ? DecliningReason::from($data['decliningReason']) : null,
            declinedAt: isset($data['declinedAt']) ? CarbonImmutable::parse($data['declinedAt']) : null,
            paidByName: $data['paidBy']['name'] ?? null,
            paidByIban: $data['paidBy']['iban'] ?? null,
        );
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isRefundRequested(): bool
    {
        return $this->status === PaymentStatus::RefundRequested;
    }

    public function isRefunded(): bool
    {
        return $this->status === PaymentStatus::Refunded;
    }

    /**
     * Whether this payment falls within your application's configured
     * refundable window (config('fib.refundable_for'), default P7D).
     *
     * This is a local, informational check — FIB is the source of truth
     * for whether FibPayment::refund() will actually succeed.
     */
    public function isRefundable(): bool
    {
        if (! $this->isPaid() || $this->paidAt === null) {
            return false;
        }

        $window = new CarbonInterval((string) config('fib.refundable_for', 'P7D'));

        return $this->paidAt->add($window)->isFuture();
    }
}
