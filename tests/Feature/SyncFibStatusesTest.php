<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Nizaamomer\LaravelFib\Enums\Payments\PaymentStatus;
use Nizaamomer\LaravelFib\Enums\Payouts\PayoutStatus;
use Nizaamomer\LaravelFib\Models\FibPayment;
use Nizaamomer\LaravelFib\Models\FibPayout;

it('re-checks pending payments and payouts', function () {
    FibPayment::create([
        'account' => 'default',
        'payment_id' => '4d6f7625-60f7-48e3-82e3-b4592a4eb993',
        'amount' => 500,
        'currency' => 'IQD',
        'status' => PaymentStatus::Unpaid,
    ]);

    FibPayout::create([
        'account' => 'default',
        'payout_id' => '40a03031-691e-4fc3-a689-1e8447b5d591',
        'target_account_iban' => 'IQ23FIQB004073628710001',
        'amount' => 1000,
        'currency' => 'IQD',
        'status' => PayoutStatus::Created,
    ]);

    Http::fake([
        '*/auth/realms/fib-online-shop/protocol/openid-connect/token' => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 60,
        ], 200),
        '*/protected/v1/payments/*/status' => Http::response([
            'paymentId' => '4d6f7625-60f7-48e3-82e3-b4592a4eb993',
            'status' => 'PAID',
            'validUntil' => '2022-01-31T12:26:12.544Z',
            'paidAt' => '2022-01-31T12:20:00.000Z',
            'amount' => ['amount' => 500, 'currency' => 'IQD'],
            'decliningReason' => null,
            'declinedAt' => null,
            'paidBy' => null,
        ], 200),
        '*/protected/v1/payouts/*' => Http::response([
            'payoutId' => '40a03031-691e-4fc3-a689-1e8447b5d591',
            'status' => 'AUTHORIZED',
            'targetAccountIban' => 'IQ23FIQB004073628710001',
            'description' => null,
            'amount' => ['amount' => 1000, 'currency' => 'IQD'],
            'authorizedAt' => '2025-03-09T08:45:52.516174Z',
            'failedAt' => null,
        ], 200),
    ]);

    $this->artisan('fib:sync-statuses')->assertExitCode(0);

    expect(FibPayment::query()->where('payment_id', '4d6f7625-60f7-48e3-82e3-b4592a4eb993')->value('status'))
        ->toBe(PaymentStatus::Paid);

    expect(FibPayout::query()->where('payout_id', '40a03031-691e-4fc3-a689-1e8447b5d591')->value('status'))
        ->toBe(PayoutStatus::Authorized);
});
