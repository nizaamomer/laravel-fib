<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Services\Payouts;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Nizaamomer\LaravelFib\Contracts\FibAuthServiceContract;
use Nizaamomer\LaravelFib\Contracts\Payouts\FibPayoutServiceContract;
use Nizaamomer\LaravelFib\Data\Payouts\PayoutData;
use Nizaamomer\LaravelFib\Data\Payouts\PayoutStatusData;
use Nizaamomer\LaravelFib\Events\Payouts\PayoutCreated;
use Nizaamomer\LaravelFib\Events\Payouts\PayoutStatusUpdated;
use Nizaamomer\LaravelFib\Exceptions\FibAccountException;
use Nizaamomer\LaravelFib\Exceptions\FibPayoutException;

final class FibPayoutService implements FibPayoutServiceContract
{
    /**
     * Basic ISO 13616 IBAN shape check: 2 letters, 2 check digits, then
     * 11-30 alphanumeric characters. Not a full checksum/registry
     * validation, just enough to reject obviously malformed input before
     * it is sent to FIB or interpolated into a request URL.
     */
    private const IBAN_PATTERN = '/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/';

    public function __construct(
        private readonly FibAuthServiceContract $auth,
    ) {}

    public function create(
        float $amount,
        string $targetAccountIban,
        ?string $description = null,
        ?string $currency = null,
        ?string $account = null,
    ): PayoutData {
        if ($amount <= 0) {
            throw FibPayoutException::invalidAmount($amount);
        }

        $iban = strtoupper(str_replace(' ', '', $targetAccountIban));

        if (! preg_match(self::IBAN_PATTERN, $iban)) {
            throw FibPayoutException::invalidIban($targetAccountIban);
        }

        $account ??= (string) config('fib.default');
        $currency ??= (string) config('fib.currency', 'IQD');

        $payload = array_filter([
            'amount' => [
                'amount' => (int) round($amount),
                'currency' => $currency,
            ],
            'targetAccountIban' => $iban,
            'description' => $description,
        ], fn ($value) => $value !== null);

        $response = $this->client($account)->post('/protected/v1/payouts', $payload);

        if ($response->failed()) {
            throw FibPayoutException::requestFailed('create payout', $response->status(), $response->body());
        }

        $payout = PayoutData::fromArray($response->json());

        PayoutCreated::dispatch($payout, $account, $amount, $iban, $description, $currency);

        return $payout;
    }

    /**
     * Authorizes a previously created payout, releasing funds to the
     * recipient. This moves real money — gate calls to this method behind
     * your own application-level authorization/approval workflow.
     */
    public function authorize(string $payoutId, ?string $account = null): bool
    {
        $account ??= (string) config('fib.default');

        $response = $this->client($account)->post('/protected/v1/payouts/'.rawurlencode($payoutId).'/authorize');

        if ($response->failed()) {
            throw FibPayoutException::requestFailed('authorize payout', $response->status(), $response->body());
        }

        return true;
    }

    public function details(string $payoutId, ?string $account = null): PayoutStatusData
    {
        $account ??= (string) config('fib.default');

        $response = $this->client($account)->get('/protected/v1/payouts/'.rawurlencode($payoutId));

        if ($response->failed()) {
            throw FibPayoutException::requestFailed('get payout details', $response->status(), $response->body());
        }

        $status = PayoutStatusData::fromArray($response->json());

        PayoutStatusUpdated::dispatch($status, $account);

        return $status;
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
