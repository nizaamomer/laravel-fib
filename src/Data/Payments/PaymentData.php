<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Data\Payments;

use Carbon\CarbonImmutable;

final readonly class PaymentData
{
    public function __construct(
        public string $paymentId,
        public string $readableCode,
        public string $qrCode,
        public ?string $personalAppLink,
        public ?string $businessAppLink,
        public ?string $corporateAppLink,
        public CarbonImmutable $validUntil,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            paymentId: $data['paymentId'],
            readableCode: $data['readableCode'],
            qrCode: $data['qrCode'],
            personalAppLink: $data['personalAppLink'] ?? null,
            businessAppLink: $data['businessAppLink'] ?? null,
            corporateAppLink: $data['corporateAppLink'] ?? null,
            validUntil: CarbonImmutable::parse($data['validUntil']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'paymentId' => $this->paymentId,
            'readableCode' => $this->readableCode,
            'qrCode' => $this->qrCode,
            'personalAppLink' => $this->personalAppLink,
            'businessAppLink' => $this->businessAppLink,
            'corporateAppLink' => $this->corporateAppLink,
            'validUntil' => $this->validUntil->toIso8601String(),
        ];
    }
}
