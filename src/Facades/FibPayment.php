<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Facades;

use Illuminate\Support\Facades\Facade;
use Nizaamomer\LaravelFib\Contracts\Payments\FibPaymentServiceContract;
use Nizaamomer\LaravelFib\Data\Payments\PaymentData;
use Nizaamomer\LaravelFib\Data\Payments\PaymentStatusData;
use Nizaamomer\LaravelFib\Data\Payments\RefundData;
use Nizaamomer\LaravelFib\Enums\Payments\PaymentCategory;

/**
 * @method static PaymentData create(float $amount, ?string $description = null, ?string $callbackUrl = null, ?string $redirectUri = null, ?string $expiresIn = null, ?PaymentCategory $category = null, ?string $account = null)
 * @method static PaymentStatusData status(string $paymentId, ?string $account = null)
 * @method static bool cancel(string $paymentId, ?string $account = null)
 * @method static RefundData refund(string $paymentId, ?string $account = null)
 *
 * @see FibPaymentServiceContract
 */
class FibPayment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FibPaymentServiceContract::class;
    }
}
