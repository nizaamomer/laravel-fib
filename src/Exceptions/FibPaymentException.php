<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Exceptions;

use InvalidArgumentException;
use RuntimeException;

class FibPaymentException extends RuntimeException
{
    public static function requestFailed(string $action, int $status, string $body): self
    {
        return new self("FIB {$action} request failed with status {$status}: {$body}");
    }

    public static function invalidAmount(float $amount): InvalidArgumentException
    {
        return new InvalidArgumentException("Payment amount must be greater than zero, got {$amount}.");
    }

    public static function invalidRefundableWindow(string $refundableFor): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "fib.refundable_for [{$refundableFor}] must be between 12 hours (PT12H) and 7 days (P7D)."
        );
    }
}
