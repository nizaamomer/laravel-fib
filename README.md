# Laravel FIB Payment & Payout SDK

<p>
<a href="https://packagist.org/packages/nizaamomer/laravel-fib"><img src="https://img.shields.io/packagist/v/nizaamomer/laravel-fib.svg?style=flat-square&label=Packagist&color=orange" alt="Latest Version on Packagist"></a>
<a href="https://github.com/nizaamomer/laravel-fib/actions"><img src="https://img.shields.io/github/actions/workflow/status/nizaamomer/laravel-fib/run-tests.yml?branch=main&label=Tests&style=flat-square" alt="Tests"></a>
<a href="https://packagist.org/packages/nizaamomer/laravel-fib"><img src="https://img.shields.io/packagist/dt/nizaamomer/laravel-fib.svg?style=flat-square&label=Downloads&color=blue" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/nizaamomer/laravel-fib"><img src="https://img.shields.io/packagist/php-v/nizaamomer/laravel-fib.svg?style=flat-square&label=PHP&color=777bb4" alt="PHP Version"></a>
<a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-ff2d20?style=flat-square" alt="Laravel Version"></a>
<a href="LICENSE.md"><img src="https://img.shields.io/packagist/l/nizaamomer/laravel-fib.svg?style=flat-square&color=success" alt="License"></a>
</p>

A modern Laravel SDK for [First Iraqi Bank (FIB)](https://fib.iq) — **payments**, **payouts**, and **refunds** in one package, with typed DTOs, enums, multi-account support, automatic status persistence, and a webhook-safe verification flow.

Built by [Nizaam Omer](https://nizaamomer.com) — [nizaamomer.com](https://nizaamomer.com)

## Table of Contents

- [Why one package?](#why-one-package)
- [Requirements](#requirements)
- [Installation](#installation)
- [Payments](#payments)
  - [Creating a payment](#creating-a-payment)
  - [Checking payment status](#checking-payment-status)
  - [Cancelling a payment](#cancelling-a-payment)
  - [Refunding a payment](#refunding-a-payment)
  - [Handling the payment callback](#handling-the-payment-callback--never-trust-the-webhook-body)
  - [Missed webhooks: the sync command](#missed-webhooks-the-sync-command)
- [Payouts](#payouts)
- [Automatic persistence](#automatic-persistence)
- [Full example](#full-example)
- [Security](#security)
- [Testing](#testing)
- [FIB API Reference](#fib-api-reference)
- [Changelog](#changelog)
- [Author](#author)
- [License](#license)

## Why one package?

Payments, payouts, and refunds all share the same OAuth2 client-credentials flow, the same `base_url`, and the same per-account credentials — splitting them into separate packages would mean authenticating multiple times and configuring everything twice. `FibPayment` handles money coming **in** (and refunds going back out), `FibPayout` handles money going **out**, both backed by one shared, cached OAuth token per account.

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

Run the migrations — creates `fib_payments`, `fib_payouts` and `fib_refunds`; every payment, payout, and refund is persisted automatically, no manual tracking code needed:

```bash
php artisan migrate
```

Add your FIB credentials to `.env`:

```env
FIB_DEFAULT_ACCOUNT=default                    # which "accounts" entry in config/fib.php to use by default
FIB_BASE_URL=https://fib.stage.fib.iq          # stage (test). Production is https://fib.prod.fib.iq
FIB_CLIENT_ID=your-client-id                   # provided by FIB
FIB_CLIENT_SECRET=your-client-secret           # provided by FIB — keep this out of version control
FIB_CURRENCY=IQD                               # default currency for payments and payouts
FIB_CALLBACK_URL=https://your-app.test/fib/callback  # FIB POSTs payment status changes here
FIB_REFUNDABLE_FOR=P7D                         # optional, ISO-8601 duration — how long a payment stays refundable, defaults to P7D
```

<!--
### Multiple accounts

Add more entries under `accounts` in `config/fib.php` to accept payments/send payouts through multiple FIB business or corporate accounts, then pass the account name as the last argument of any SDK call:

```php
FibPayment::create(500.00, account: 'second_account');
```
-->

## Payments

### Creating a payment

```php
use Nizaamomer\LaravelFib\Facades\FibPayment;

$payment = FibPayment::create(
    amount: 500.00,
    description: 'Order #1042',
    // callbackUrl and currency are optional — they fall back to
    // FIB_CALLBACK_URL and FIB_CURRENCY automatically when omitted
);

$payment->paymentId;      // string
$payment->readableCode;   // e.g. "S3LE-NZ2S-ZNGF"
$payment->qrCode;         // base64 data URL
$payment->businessAppLink;
$payment->validUntil;     // CarbonImmutable
```

A few more optional arguments are available when you need them:

```php
use Nizaamomer\LaravelFib\Enums\Payments\PaymentCategory;

FibPayment::create(
    amount: 500.00,
    redirectUri: 'https://your-app.test/orders/1042', // where FIB redirects the user after paying/cancelling
    expiresIn: 'PT8H6M12.345S',                        // ISO-8601 duration — expire the payment after this long
    category: PaymentCategory::Ecommerce,              // defaults to UNKNOWN on FIB's side if omitted
);
```

Or resolve the contract instead of using the facade:

```php
use Nizaamomer\LaravelFib\Contracts\Payments\FibPaymentServiceContract;

public function __construct(private FibPaymentServiceContract $payments) {}
```

### Checking payment status

```php
$status = FibPayment::status($payment->paymentId);

$status->status;         // PaymentStatus::Paid | Unpaid | Declined | RefundRequested | Refunded
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

Only payments with `PAID` status, within their refundable window, can be refunded.

```php
$refund = FibPayment::refund($payment->paymentId);

$refund->isSuccessful(); // bool — true only on HTTP 202
$refund->status;         // RefundStatus::Success | Failed
$refund->traceId;        // ?string, present on failure — quote this to FIB support
$refund->errorCodes;     // ?array<string>, present on failure
```

After accepting a refund (202), FIB moves the payment through `REFUND_REQUESTED` and then `REFUNDED` — call `FibPayment::status()` again (or let `fib:sync-statuses` do it) to see the final state. A `PaymentRefundRequested` event fires either way and is persisted to `fib_refunds`, linked back to the `fib_payments` row.

### Handling the payment callback — never trust the webhook body

FIB's webhook payload is **not signed**. Anyone who learns or guesses your callback URL could POST a fake `"status": "PAID"` body. Always treat the callback as a *trigger to re-check*, never as the source of truth. FIB expects your handler to respond with `202`, and will retry up to 5 times if it doesn't get a response:

```php
Route::post('/fib/callback', function (\Illuminate\Http\Request $request) {
    // Re-fetch the authoritative status from FIB — do not trust $request->status
    $status = FibPayment::status($request->input('id'));

    if ($status->isPaid()) {
        // fulfil the order
    }

    return response()->noContent(202);
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
    // currency is optional — falls back to FIB_CURRENCY automatically
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

Every `create()`, status/details, and refund call fires an event (`PaymentCreated`, `PaymentStatusUpdated`, `PaymentRefundRequested`, `PayoutCreated`, `PayoutStatusUpdated`) that this package listens to and upserts into `fib_payments`, `fib_payouts`, and `fib_refunds` automatically — no manual tracking code required.

### Linking a payment to your own model

The `payable` relation is optional and one-directional from our side: `fib_payments`/`fib_payouts` have nullable `payable_type`/`payable_id` columns with no real foreign-key constraint, so your app's tables never need to know this package exists.

Add the reverse relation on your own model (no migration needed on your side — the columns already live on our tables):

```php
// app/Models/Order.php
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Nizaamomer\LaravelFib\Models\FibPayment;

class Order extends Model
{
    public function fibPayment(): MorphOne
    {
        return $this->morphOne(FibPayment::class, 'payable');
    }
}
```

Then associate it right after creating the payment — our `PaymentCreated` event fires synchronously, so the `fib_payments` row already exists by the time `create()` returns:

```php
use Nizaamomer\LaravelFib\Models\FibPayment;

$payment = FibPayment::create(amount: $order->total, description: "Order #{$order->id}");

FibPayment::where('payment_id', $payment->paymentId)->first()
    ?->payable()->associate($order)->save();
```

From then on, read it back from either side:

```php
$order->fibPayment->status;   // straight off your own model
$fibPayment->payable;          // the Order instance, straight off ours
```

### Listening to events yourself

```php
Event::listen(\Nizaamomer\LaravelFib\Events\Payments\PaymentStatusUpdated::class, function ($event) {
    // $event->status, $event->account
});
```

## Full example

[`docs/examples/PaymentController.php`](docs/examples/PaymentController.php) is a complete, heavily-commented controller covering every public method in the package — creating and checking a payment, cancelling, refunding, the webhook callback, and the payout create → authorize → details flow. It's illustrative (not autoloaded), so copy what you need into your own app.

## Security

- **TLS verification is never disabled.** This SDK does not expose a way to set Guzzle's `verify => false` — if you're migrating from an older internal integration that disabled certificate verification, remove that, it defeats HTTPS's protection against man-in-the-middle attacks.
- **Webhook payloads are not trusted.** `FibPayment::status()` always calls FIB directly; use it inside your callback handler instead of reading `status` off the request body (see above).
- **IDs are URL-encoded** before being interpolated into request paths, and **IBANs are format-validated** before a payout is created, so malformed or malicious input from your own callers can't corrupt the outgoing request.
- **Amounts must be greater than zero** — validated before any request is sent.
- **Credentials live in `.env`,** never in version control. Rotate `FIB_CLIENT_SECRET` immediately if it's ever exposed.
- **Tokens are cached, never logged.** The auth token is stored in your configured cache store, scoped per account, and is never written to logs or exceptions.

If you discover a security issue, please email [nizaamomer@gmail.com](mailto:nizaamomer@gmail.com) instead of using the public issue tracker.

## Testing

```bash
composer test        # Pest
composer analyse      # Larastan / PHPStan (level 8)
composer format        # Laravel Pint
```

## FIB API Reference

See the [FIB Online Payments documentation](https://fib.iq/all-integrations/) for the underlying REST API this SDK wraps.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what's changed in each release.

## Author

**Nizaam Omer** — [nizaamomer.com](https://nizaamomer.com) · [nizaamomer@gmail.com](mailto:nizaamomer@gmail.com)

## License

MIT. See [LICENSE.md](LICENSE.md).
