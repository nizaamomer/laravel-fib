<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Nizaamomer\LaravelFib\Contracts\FibAuthServiceContract;
use Nizaamomer\LaravelFib\Exceptions\FibAccountException;

it('requests and caches an access token', function () {
    Http::fake([
        '*/auth/realms/fib-online-shop/protocol/openid-connect/token' => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 60,
        ], 200),
    ]);

    $auth = app(FibAuthServiceContract::class);

    expect($auth->token('default'))->toBe('fake-token');
    expect($auth->token('default'))->toBe('fake-token');

    Http::assertSentCount(1);
});

it('refreshes the token on demand', function () {
    Http::fake([
        '*/auth/realms/fib-online-shop/protocol/openid-connect/token' => Http::sequence()
            ->push(['access_token' => 'first-token', 'expires_in' => 60])
            ->push(['access_token' => 'second-token', 'expires_in' => 60]),
    ]);

    $auth = app(FibAuthServiceContract::class);

    expect($auth->token('default'))->toBe('first-token');
    expect($auth->refreshToken('default'))->toBe('second-token');

    Http::assertSentCount(2);
});

it('throws for an unknown account', function () {
    $auth = app(FibAuthServiceContract::class);

    $auth->token('does-not-exist');
})->throws(FibAccountException::class);
