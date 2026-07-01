# Changelog

All notable changes to `nizaamomer/laravel-fib` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-01

### Added

- **Payments**: `FibPayment::create()`, `status()`, `cancel()`, `refund()`.
- **Payouts**: `FibPayout::create()`, `authorize()`, `details()`.
- Typed DTOs (`PaymentData`, `PaymentStatusData`, `PayoutData`, `PayoutStatusData`, `RefundData`) and enums (`PaymentStatus`, `PaymentCategory`, `DecliningReason`, `PayoutStatus`, `PayoutCurrency`, `RefundStatus`) for every FIB response shape.
- `PaymentStatusData::isRefundable()` policy helper backed by `FIB_REFUNDABLE_FOR`.
- Optional `redirectUri`, `expiresIn`, and `category` parameters on `FibPayment::create()`.
- Multi-account support via the `accounts` array in `config/fib.php`.
- Shared, cached OAuth2 token per account (`FibAuthService`), refreshed a few seconds before real expiry.
- Automatic persistence: every create/status/refund/payout call fires an event that upserts into `fib_payments`, `fib_payouts`, and `fib_refunds` — no manual tracking code needed.
- `php artisan fib:sync-statuses` — polls pending payments/payouts directly against the FIB API, covering missed webhooks and the fact that payouts have no webhook at all.
- `payable` polymorphic relation on `FibPayment`/`FibPayout` models to link a payment/payout to your own order/subscription models.

### Security

- TLS certificate verification is always enabled and cannot be disabled through this SDK.
- Webhook payloads are never trusted — `FibPayment::status()` always re-verifies against FIB directly.
- IDs are URL-encoded before being interpolated into request paths.
- IBANs are format-validated before a payout is created.
- Amounts and the `refundableFor` window are validated against FIB's constraints (greater than zero; 12 hours–7 days) before any request is sent.

[1.0.0]: https://github.com/nizaamomer/laravel-fib/releases/tag/v1.0.0
