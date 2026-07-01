<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Nizaamomer\LaravelFib\Contracts\Payouts\FibPayoutServiceContract;
use Nizaamomer\LaravelFib\Data\Payouts\PayoutData;
use Nizaamomer\LaravelFib\Enums\Payouts\PayoutStatus;
use Nizaamomer\LaravelFib\Models\FibPayout;

beforeEach(function () {
    Http::fake([
        '*/auth/realms/fib-online-shop/protocol/openid-connect/token' => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 60,
        ], 200),
    ]);
});

it('creates a payout and persists it', function () {
    Http::fake([
        '*/protected/v1/payouts' => Http::response([
            'payoutId' => '40a03031-691e-4fc3-a689-1e8447b5d591',
        ], 200),
        '*/auth/realms/fib-online-shop/protocol/openid-connect/token' => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 60,
        ], 200),
    ]);

    $payout = app(FibPayoutServiceContract::class)->create(
        amount: 1000,
        targetAccountIban: 'IQ23FIQB004073628710001',
        description: 'test',
    );

    expect($payout)->toBeInstanceOf(PayoutData::class)
        ->and($payout->payoutId)->toBe('40a03031-691e-4fc3-a689-1e8447b5d591');

    expect(FibPayout::query()->where('payout_id', $payout->payoutId)->exists())->toBeTrue();
});

it('rejects an invalid iban', function () {
    app(FibPayoutServiceContract::class)->create(1000, 'not-an-iban');
})->throws(InvalidArgumentException::class);

it('rejects a non-positive amount', function () {
    app(FibPayoutServiceContract::class)->create(0, 'IQ23FIQB004073628710001');
})->throws(InvalidArgumentException::class);

it('authorizes a payout', function () {
    Http::fake([
        '*/protected/v1/payouts/*/authorize' => Http::response(null, 200),
    ]);

    $result = app(FibPayoutServiceContract::class)->authorize('40a03031-691e-4fc3-a689-1e8447b5d591');

    expect($result)->toBeTrue();
});

it('gets payout details and persists status updates', function () {
    Http::fake([
        '*/protected/v1/payouts/*' => Http::response([
            'payoutId' => '40a03031-691e-4fc3-a689-1e8447b5d591',
            'status' => 'AUTHORIZED',
            'targetAccountIban' => 'IQ23FIQB004073628710001',
            'description' => 'test',
            'amount' => ['amount' => 1000, 'currency' => 'IQD'],
            'authorizedAt' => '2025-03-09T08:45:52.516174Z',
            'failedAt' => null,
        ], 200),
    ]);

    $status = app(FibPayoutServiceContract::class)->details('40a03031-691e-4fc3-a689-1e8447b5d591');

    expect($status->status)->toBe(PayoutStatus::Authorized)
        ->and($status->isAuthorized())->toBeTrue();

    expect(FibPayout::query()->where('payout_id', $status->payoutId)->value('status'))
        ->toBe(PayoutStatus::Authorized);
});
