# Laravel FIB

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nizaamomer/laravel-fib.svg?style=flat-square)](https://packagist.org/packages/nizaamomer/laravel-fib)
[![Tests](https://img.shields.io/github/actions/workflow/status/nizaamomer/laravel-fib/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nizaamomer/laravel-fib/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/nizaamomer/laravel-fib.svg?style=flat-square)](https://packagist.org/packages/nizaamomer/laravel-fib)

A modern Laravel SDK for [First Iraqi Bank (FIB)](https://fib.iq) — **online payments** and **payouts** in one package, with typed DTOs, enums, multi-account support, automatic status persistence, and a webhook-safe status verification flow. Built for Laravel 11, 12 and 13.

Made by [Nizaam Omer](https://nizaamomer.com).

## Why one package for both?

Payments and payouts share the same OAuth2 client-credentials flow, the same `base_url`, and the same per-account credentials — splitting them into two packages would mean authenticating twice and configuring everything twice. `Nizaamomer\LaravelFib\Facades\FibPayment` handles money coming **in**, `Nizaamomer\LaravelFib\Facades\FibPayout` handles money going **out**, both backed by one shared, cached OAuth token per account.

## Requirements

- PHP 8.2+
- Laravel 11.x, 12.x or 13.x

## Installation

```bash
composer require nizaamomer/laravel-fib
```

Publish the config file:

```bash
php artisan vendor:publish --tag="fib-config"
```

Run the migrations (creates `fib_payments`, `fib_payouts` and `fib_refunds` tables — every payment/payout/refund is automatically persisted for you, no manual tracking code needed):

```bash
php artisan migrate
```

Add your FIB credentials to `.env`:

```env
FIB_DEFAULT_ACCOUNT=default
FIB_BASE_URL=https://fib.stage.fib.iq
FIB_CLIENT_ID=your-client-id
FIB_CLIENT_SECRET=your-client-secret
FIB_CURRENCY=IQD
FIB_CALLBACK_URL=https://your-app.test/fib/callback
FIB_REFUNDABLE_FOR=P7D                        # optional, defaults to P7D
```

### Multiple accounts

Add more entries under `accounts` in `config/fib.php` to accept payments/send payouts through multiple FIB business or corporate accounts, then pass the account name as the last argument of any SDK call:

```php
FibPayment::create(500.00, account: 'second_account');
```

## Payments

### Creating a payment

```php
use Nizaamomer\LaravelFib\Facades\FibPayment;

$payment = FibPayment::create(
    amount: 500.00,
    description: 'Order #1042',
    callbackUrl: route('fib.callback'),
);

$payment->paymentId;      // string
$payment->readableCode;   // e.g. "S3LE-NZ2S-ZNGF"
$payment->qrCode;         // base64 data URL
$payment->businessAppLink;
$payment->validUntil;     // CarbonImmutable
```

Or resolve the contract instead of using the facade:

```php
use Nizaamomer\LaravelFib\Contracts\Payments\FibPaymentServiceContract;

public function __construct(private FibPaymentServiceContract $payments) {}
```

### Checking payment status

```php
$status = FibPayment::status($payment->paymentId);

$status->status;         // PaymentStatus::Paid | Unpaid | Declined
$status->isPaid();       // bool
$status->isRefundable(); // bool, based on FIB_REFUNDABLE_FOR
$status->amount;         // float
$status->paidAt;         // ?CarbonImmutable
```

### Cancelling a payment

```php
FibPayment::cancel($payment->paymentId);
```

### Refunding a payment

> **⚠️ Undocumented endpoint.** `/payments/{id}/refund` does not appear anywhere in FIB's published API reference — this was reverse-engineered by reading First Iraqi Bank's own [fib-laravel-payment-sdk](https://packagist.org/packages/first-iraqi-bank/fib-laravel-payment-sdk) source, not from official docs. Verify the behavior against your sandbox account before relying on it in production, and don't be surprised if FIB changes it without notice.

```php
$refund = FibPayment::refund($payment->paymentId);

$refund->isSuccessful(); // bool — true only on HTTP 202
$refund->status;         // RefundStatus::Success | Failed
$refund->traceId;        // ?string, present on failure — quote this to FIB support
$refund->errorCodes;     // ?array<string>, present on failure
```

A `PaymentRefundRequested` event fires either way and is persisted to `fib_refunds`, linked to the `fib_payments` row via `FibPayment::refund()`.

Every `create()` call also sends `refundableFor` (from `FIB_REFUNDABLE_FOR`) to FIB, again mirroring the vendor SDK rather than official docs — this asks FIB to only allow refunds within that window, on top of your own `PaymentStatusData::isRefundable()` local policy check.

### Handling the payment callback — never trust the webhook body

FIB's webhook payload is **not signed**. Anyone who learns or guesses your callback URL could POST a fake `"status": "PAID"` body. Always treat the callback as a *trigger to re-check*, never as the source of truth:

```php
Route::post('/fib/callback', function (\Illuminate\Http\Request $request) {
    // Re-fetch the authoritative status from FIB — do not trust $request->status
    $status = FibPayment::status($request->input('id'));

    if ($status->isPaid()) {
        // fulfil the order
    }

    return response()->noContent();
})->name('fib.callback')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
  ->middleware('throttle:60,1');
```

The route needs CSRF exempted (FIB won't send a CSRF token) and should be throttled, since it's a public unauthenticated endpoint.

### Missed webhooks: the sync command

Payments can be marked `UNPAID` in your database if a webhook is delayed, dropped, or never configured. Run:

```bash
php artisan fib:sync-statuses
```

to re-check every pending payment (and pending payout — see below) directly against the FIB API. Schedule it in `bootstrap/app.php`:

```php
->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule) {
    $schedule->command('fib:sync-statuses')->everyFiveMinutes();
})
```

## Payouts

Payouts move money **out** of your FIB account to a recipient's IBAN. This is a two-step flow: create, then authorize.

```php
use Nizaamomer\LaravelFib\Facades\FibPayout;

$payout = FibPayout::create(
    amount: 1000,
    targetAccountIban: 'IQ23FIQB004073628710001',
    description: 'Vendor payment',
    currency: 'IQD', // IQD, USD or EUR
);

$payout->payoutId;
```

> **⚠️ Authorizing a payout releases real funds immediately.** Gate calls to `authorize()` behind your own application-level approval step (e.g. a second admin confirmation) — this SDK does not add artificial friction here, that responsibility belongs to your app's authorization policy.

```php
FibPayout::authorize($payout->payoutId);

$details = FibPayout::details($payout->payoutId);
$details->status;         // PayoutStatus::Created | Authorized | Failed
$details->isAuthorized();
$details->isFailed();
$details->failureReason;
```

Payouts have **no webhook** — the only way to learn a payout's final status is to call `details()` again, or rely on `php artisan fib:sync-statuses`.

## Automatic persistence

Every `create()` and status/details call fires an event (`PaymentCreated`, `PaymentStatusUpdated`, `PayoutCreated`, `PayoutStatusUpdated`) that this package listens to and upserts into the `fib_payments` / `fib_payouts` tables automatically — no manual tracking code required. Link your own models via the `payable` polymorphic relation if you need to associate a payment with an order/subscription:

```php
use Nizaamomer\LaravelFib\Models\FibPayment;

FibPayment::where('payment_id', $paymentId)->first()?->payable()->associate($order)->save();
```

Or listen to the events yourself for custom side effects:

```php
Event::listen(\Nizaamomer\LaravelFib\Events\Payments\PaymentStatusUpdated::class, function ($event) {
    // $event->status, $event->account
});
```

## Security

- **TLS verification is never disabled.** This SDK does not expose a way to set Guzzle's `verify => false` — if you're migrating from an older internal integration that disabled certificate verification, remove that, it defeats HTTPS's protection against man-in-the-middle attacks.
- **Webhook payloads are not trusted.** `FibPayment::status()` always calls FIB directly; use it inside your callback handler instead of reading `status` off the request body (see above).
- **IDs are URL-encoded** before being interpolated into request paths, and **IBANs are format-validated** before a payout is created, so malformed or malicious input from your own callers can't corrupt the outgoing request.
- **Amounts must be greater than zero** — validated before any request is sent.
- **Credentials live in `.env`,** never in version control. Rotate `FIB_CLIENT_SECRET` immediately if it's ever exposed (e.g. committed, pasted into a chat, or leaked in a Postman export).
- **Tokens are cached, never logged.** The auth token is stored in your configured cache store, scoped per account, and is never written to logs or exceptions.

If you discover a security issue, please email nizam.tci200237559463@spu.edu.iq instead of using the public issue tracker.

## Testing

```bash
composer test        # Pest
composer analyse      # Larastan / PHPStan (level 8)
composer format        # Laravel Pint
```

## FIB API Reference

See the [FIB Online Payments documentation](https://fib.iq/all-integrations/) for the underlying REST API this SDK wraps.

## Author

Built and maintained by [Nizaam Omer](https://nizaamomer.com).

## License

MIT. See [LICENSE.md](LICENSE.md).
