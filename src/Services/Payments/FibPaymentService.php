<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Nizaamomer\LaravelFib\Contracts\FibAuthServiceContract;
use Nizaamomer\LaravelFib\Contracts\Payments\FibPaymentServiceContract;
use Nizaamomer\LaravelFib\Data\Payments\PaymentData;
use Nizaamomer\LaravelFib\Data\Payments\PaymentStatusData;
use Nizaamomer\LaravelFib\Data\Payments\RefundData;
use Nizaamomer\LaravelFib\Events\Payments\PaymentCreated;
use Nizaamomer\LaravelFib\Events\Payments\PaymentRefundRequested;
use Nizaamomer\LaravelFib\Events\Payments\PaymentStatusUpdated;
use Nizaamomer\LaravelFib\Exceptions\FibAccountException;
use Nizaamomer\LaravelFib\Exceptions\FibPaymentException;

final class FibPaymentService implements FibPaymentServiceContract
{
    public function __construct(
        private readonly FibAuthServiceContract $auth,
    ) {}

    public function create(
        float $amount,
        ?string $description = null,
        ?string $callbackUrl = null,
        ?string $account = null,
    ): PaymentData {
        if ($amount <= 0) {
            throw FibPaymentException::invalidAmount($amount);
        }

        $account ??= (string) config('fib.default');
        $currency = (string) config('fib.currency', 'IQD');

        $payload = array_filter([
            'monetaryValue' => [
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
            ],
            'statusCallbackUrl' => $callbackUrl ?? config('fib.callback_url'),
            'description' => $description !== null ? mb_substr($description, 0, 50) : null,
            // Not documented in FIB's public API reference; sent by First
            // Iraqi Bank's own SDK, so mirrored here for parity.
            'refundableFor' => config('fib.refundable_for'),
        ], fn ($value) => $value !== null);

        $response = $this->client($account)->post('/protected/v1/payments', $payload);

        if ($response->failed()) {
            throw FibPaymentException::requestFailed('create payment', $response->status(), $response->body());
        }

        $payment = PaymentData::fromArray($response->json());

        PaymentCreated::dispatch($payment, $account, $amount, $currency);

        return $payment;
    }

    /**
     * Fetches the authoritative payment status directly from FIB.
     *
     * Always use this to confirm a payment's outcome — never trust the
     * `status` field from a webhook callback payload on its own, since it
     * is not signed and could be spoofed by a third party who guesses or
     * intercepts your callback URL.
     */
    public function status(string $paymentId, ?string $account = null): PaymentStatusData
    {
        $account ??= (string) config('fib.default');

        $response = $this->client($account)->get('/protected/v1/payments/'.rawurlencode($paymentId).'/status');

        if ($response->failed()) {
            throw FibPaymentException::requestFailed('check payment status', $response->status(), $response->body());
        }

        $status = PaymentStatusData::fromArray($response->json());

        PaymentStatusUpdated::dispatch($status, $account);

        return $status;
    }

    public function cancel(string $paymentId, ?string $account = null): bool
    {
        $account ??= (string) config('fib.default');

        $response = $this->client($account)->post('/protected/v1/payments/'.rawurlencode($paymentId).'/cancel');

        if ($response->failed()) {
            throw FibPaymentException::requestFailed('cancel payment', $response->status(), $response->body());
        }

        return true;
    }

    public function refund(string $paymentId, ?string $account = null): RefundData
    {
        $account ??= (string) config('fib.default');

        $response = $this->client($account)->post('/protected/v1/payments/'.rawurlencode($paymentId).'/refund');

        $refund = match (true) {
            $response->status() === 202 => RefundData::accepted($paymentId),
            $response->failed() => RefundData::declined($paymentId, $response->json() ?? []),
            default => throw FibPaymentException::requestFailed('refund payment', $response->status(), $response->body()),
        };

        PaymentRefundRequested::dispatch($refund, $account);

        return $refund;
    }

    private function client(string $account): PendingRequest
    {
        $baseUrl = config("fib.accounts.{$account}.base_url");

        if (! $baseUrl) {
            throw FibAccountException::unknownAccount($account);
        }

        return Http::baseUrl((string) $baseUrl)
            ->withToken($this->auth->token($account))
            ->timeout((int) config('fib.http.timeout', 15))
            ->retry(
                (int) config('fib.http.retry_times', 1),
                (int) config('fib.http.retry_sleep_ms', 200),
            )
            ->acceptJson()
            ->asJson();
    }
}
