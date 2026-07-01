<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default FIB Account
    |--------------------------------------------------------------------------
    |
    | The connection used when no explicit account is requested. Matches a
    | key in the "accounts" array below. Payments and payouts share the same
    | account pool since they use the same OAuth2 client credentials.
    |
    */
    'default' => env('FIB_DEFAULT_ACCOUNT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | FIB Accounts
    |--------------------------------------------------------------------------
    |
    | Each account holds its own client credentials so a single application
    | can accept payments/send payouts through multiple FIB business or
    | corporate accounts.
    |
    */
    'accounts' => [
        'default' => [
            'base_url' => env('FIB_BASE_URL', 'https://fib.stage.fib.iq'),
            'client_id' => env('FIB_CLIENT_ID'),
            'client_secret' => env('FIB_CLIENT_SECRET'),
            'grant_type' => env('FIB_GRANT_TYPE', 'client_credentials'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for payments (FIB currently only accepts IQD for
    | payments). Payouts additionally support USD and EUR.
    |
    */
    'currency' => env('FIB_CURRENCY', 'IQD'),

    /*
    |--------------------------------------------------------------------------
    | Callback URL
    |--------------------------------------------------------------------------
    |
    | The URL FIB will POST payment status updates to. Never trust the
    | callback payload directly — always re-verify via the status endpoint
    | (this SDK's FibPayment::status() does this for you). Leave null to
    | rely solely on polling (see the fib:sync-statuses command).
    |
    */
    'callback_url' => env('FIB_CALLBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Refundable Window
    |--------------------------------------------------------------------------
    |
    | ISO-8601 duration describing how long after being paid a payment stays
    | refundable. Sent to FIB on payment creation, and also used locally by
    | PaymentStatusData::isRefundable() as a policy check before calling
    | FibPayment::refund().
    |
    */
    'refundable_for' => env('FIB_REFUNDABLE_FOR', 'P7D'),

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    |
    | Access tokens are short-lived (FIB defaults to 60s). We cache the token
    | per account and refresh a few seconds before real expiry to avoid
    | clock-skew failures.
    |
    */
    'token_cache' => [
        'store' => env('FIB_TOKEN_CACHE_STORE'), // null = default cache store
        'safety_margin_seconds' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | TLS certificate verification is always enabled and cannot be disabled
    | through configuration — do not add "verify => false" anywhere when
    | extending this SDK, it defeats HTTPS protection against MITM attacks.
    |
    */
    'http' => [
        'timeout' => (int) env('FIB_HTTP_TIMEOUT', 15),
        'retry_times' => (int) env('FIB_HTTP_RETRY_TIMES', 1),
        'retry_sleep_ms' => (int) env('FIB_HTTP_RETRY_SLEEP_MS', 200),
    ],

];
