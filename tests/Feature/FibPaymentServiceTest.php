<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Nizaamomer\LaravelFib\Contracts\Payments\FibPaymentServiceContract;
use Nizaamomer\LaravelFib\Data\Payments\PaymentData;
use Nizaamomer\LaravelFib\Enums\Payments\PaymentStatus;
use Nizaamomer\LaravelFib\Models\FibPayment;

beforeEach(function () {
    Http::fake([
        '*/auth/realms/fib-online-shop/protocol/openid-connect/token' => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 60,
        ], 200),
    ]);
});

it('creates a payment and persists it', function () {
    Http::fake([
        '*/protected/v1/payments' => Http::response([
            'paymentId' => '9dfa724f-4784-4487-811b-63057b540503',
            'readableCode' => 'S3LE-NZ2S-ZNGF',
            'qrCode' => 'data:image/png;base64,fake',
            'personalAppLink' => 'https://personal.stage.first-iraqi-bank.co/x',
            'businessAppLink' => 'https://business.stage.first-iraqi-bank.co/x',
            'corporateAppLink' => 'https://corporate.stage.first-iraqi-bank.co/x',
            'validUntil' => '2022-01-31T12:15:44.020920Z',
        ], 202),
        '*/auth/realms/fib-online-shop/protocol/openid-connect/token' => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 60,
        ], 200),
    ]);

    $payment = app(FibPaymentServiceContract::class)->create(500.0, 'Order #1');

    expect($payment)->toBeInstanceOf(PaymentData::class)
        ->and($payment->paymentId)->toBe('9dfa724f-4784-4487-811b-63057b540503')
        ->and($payment->readableCode)->toBe('S3LE-NZ2S-ZNGF');

    expect(FibPayment::query()->where('payment_id', $payment->paymentId)->exists())->toBeTrue();
});

it('rejects a non-positive amount', function () {
    app(FibPaymentServiceContract::class)->create(0.0);
})->throws(InvalidArgumentException::class);

it('checks a payment status and persists it', function () {
    Http::fake([
        '*/protected/v1/payments/*/status' => Http::response([
            'paymentId' => '4d6f7625-60f7-48e3-82e3-b4592a4eb993',
            'status' => 'UNPAID',
            'validUntil' => '2022-01-31T12:26:12.544Z',
            'paidAt' => null,
            'amount' => ['amount' => 500, 'currency' => 'IQD'],
            'decliningReason' => null,
            'declinedAt' => null,
            'paidBy' => null,
        ], 200),
    ]);

    $status = app(FibPaymentServiceContract::class)->status('4d6f7625-60f7-48e3-82e3-b4592a4eb993');

    expect($status->status)->toBe(PaymentStatus::Unpaid)
        ->and($status->isPaid())->toBeFalse()
        ->and($status->isRefundable())->toBeFalse()
        ->and($status->amount)->toBe(500.0);

    expect(FibPayment::query()->where('payment_id', $status->paymentId)->value('status'))
        ->toBe(PaymentStatus::Unpaid);
});

it('cancels a payment', function () {
    Http::fake([
        '*/protected/v1/payments/*/cancel' => Http::response(null, 204),
    ]);

    $result = app(FibPaymentServiceContract::class)->cancel('4d6f7625-60f7-48e3-82e3-b4592a4eb993');

    expect($result)->toBeTrue();
});
