<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Nizaamomer\LaravelFib\Contracts\Payments\FibPaymentServiceContract;
use Nizaamomer\LaravelFib\Data\Payments\PaymentData;
use Nizaamomer\LaravelFib\Enums\Payments\PaymentCategory;
use Nizaamomer\LaravelFib\Enums\Payments\PaymentStatus;
use Nizaamomer\LaravelFib\Enums\Payments\RefundStatus;
use Nizaamomer\LaravelFib\Exceptions\FibPaymentException;
use Nizaamomer\LaravelFib\Models\FibPayment;
use Nizaamomer\LaravelFib\Models\FibRefund;

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

it('sends redirectUri, expiresIn and category on create', function () {
    Http::fake([
        '*/protected/v1/payments' => Http::response([
            'paymentId' => '9dfa724f-4784-4487-811b-63057b540503',
            'readableCode' => 'S3LE-NZ2S-ZNGF',
            'qrCode' => 'data:image/png;base64,fake',
            'personalAppLink' => null,
            'businessAppLink' => null,
            'corporateAppLink' => null,
            'validUntil' => '2022-01-31T12:15:44.020920Z',
        ], 202),
    ]);

    app(FibPaymentServiceContract::class)->create(
        amount: 505.0,
        redirectUri: 'https://example.test/redirect',
        expiresIn: 'PT8H6M12.345S',
        category: PaymentCategory::Pos,
    );

    Http::assertSent(function ($request) {
        return $request->url() === 'https://fib.stage.fib.iq/protected/v1/payments'
            && $request['redirectUri'] === 'https://example.test/redirect'
            && $request['expiresIn'] === 'PT8H6M12.345S'
            && $request['category'] === 'POS'
            && $request['refundableFor'] === 'P7D';
    });
});

it('rejects a refundable_for window outside FIB\'s 12h-7d range', function () {
    config()->set('fib.refundable_for', 'PT1H');

    app(FibPaymentServiceContract::class)->create(500.0);
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

it('handles a status response missing validUntil without erasing a previously known one', function () {
    FibPayment::query()->create([
        'account' => 'default',
        'payment_id' => '4d6f7625-60f7-48e3-82e3-b4592a4eb993',
        'amount' => 500,
        'currency' => 'IQD',
        'status' => PaymentStatus::Unpaid,
        'valid_until' => now()->addHour(),
    ]);

    Http::fake([
        '*/protected/v1/payments/*/status' => Http::response([
            'paymentId' => '4d6f7625-60f7-48e3-82e3-b4592a4eb993',
            'status' => 'UNPAID',
            // validUntil intentionally omitted — FIB's staging API doesn't
            // always return it on the status endpoint.
            'paidAt' => null,
            'amount' => ['amount' => 500, 'currency' => 'IQD'],
            'decliningReason' => null,
            'declinedAt' => null,
            'paidBy' => null,
        ], 200),
    ]);

    $status = app(FibPaymentServiceContract::class)->status('4d6f7625-60f7-48e3-82e3-b4592a4eb993');

    expect($status->validUntil)->toBeNull();

    expect(FibPayment::query()->where('payment_id', $status->paymentId)->value('valid_until'))
        ->not->toBeNull();
});

it('parses REFUND_REQUESTED and REFUNDED statuses without error', function (string $status) {
    Http::fake([
        '*/protected/v1/payments/*/status' => Http::response([
            'paymentId' => '4d6f7625-60f7-48e3-82e3-b4592a4eb993',
            'status' => $status,
            'validUntil' => '2022-01-31T12:26:12.544Z',
            'paidAt' => '2022-01-31T10:00:00.000Z',
            'amount' => ['amount' => 500, 'currency' => 'IQD'],
            'decliningReason' => null,
            'declinedAt' => null,
            'paidBy' => null,
        ], 200),
    ]);

    $result = app(FibPaymentServiceContract::class)->status('4d6f7625-60f7-48e3-82e3-b4592a4eb993');

    expect($result->status)->toBe(PaymentStatus::from($status));
})->with(['REFUND_REQUESTED', 'REFUNDED']);

it('cancels a payment', function () {
    Http::fake([
        '*/protected/v1/payments/*/cancel' => Http::response(null, 204),
    ]);

    $result = app(FibPaymentServiceContract::class)->cancel('4d6f7625-60f7-48e3-82e3-b4592a4eb993');

    expect($result)->toBeTrue();
});

it('accepts a refund and persists it', function () {
    FibPayment::query()->create([
        'account' => 'default',
        'payment_id' => '4d6f7625-60f7-48e3-82e3-b4592a4eb993',
        'amount' => 500,
        'currency' => 'IQD',
        'status' => PaymentStatus::Paid,
    ]);

    Http::fake([
        '*/protected/v1/payments/*/refund' => Http::response(null, 202),
    ]);

    $refund = app(FibPaymentServiceContract::class)->refund('4d6f7625-60f7-48e3-82e3-b4592a4eb993');

    expect($refund->isSuccessful())->toBeTrue()
        ->and($refund->status)->toBe(RefundStatus::Success);

    expect(FibRefund::query()->where('fib_trace_id', null)->value('status'))
        ->toBe(RefundStatus::Success);
});

it('records a declined refund with its trace id and error codes', function () {
    FibPayment::query()->create([
        'account' => 'default',
        'payment_id' => '4d6f7625-60f7-48e3-82e3-b4592a4eb993',
        'amount' => 500,
        'currency' => 'IQD',
        'status' => PaymentStatus::Paid,
    ]);

    Http::fake([
        '*/protected/v1/payments/*/refund' => Http::response([
            'traceId' => 'trace-123',
            'errors' => [['code' => 'REFUND_WINDOW_EXPIRED']],
        ], 400),
    ]);

    $refund = app(FibPaymentServiceContract::class)->refund('4d6f7625-60f7-48e3-82e3-b4592a4eb993');

    expect($refund->isSuccessful())->toBeFalse()
        ->and($refund->traceId)->toBe('trace-123')
        ->and($refund->errorCodes)->toBe(['REFUND_WINDOW_EXPIRED']);

    expect(FibRefund::query()->where('fib_trace_id', 'trace-123')->value('status'))
        ->toBe(RefundStatus::Failed);
});

it('throws on an unexpected refund response', function () {
    Http::fake([
        '*/protected/v1/payments/*/refund' => Http::response(['ok' => true], 200),
    ]);

    app(FibPaymentServiceContract::class)->refund('4d6f7625-60f7-48e3-82e3-b4592a4eb993');
})->throws(FibPaymentException::class);
