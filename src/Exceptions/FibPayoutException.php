<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Exceptions;

use InvalidArgumentException;
use RuntimeException;

class FibPayoutException extends RuntimeException
{
    public static function requestFailed(string $action, int $status, string $body): self
    {
        return new self("FIB {$action} request failed with status {$status}: {$body}");
    }

    public static function invalidAmount(float $amount): InvalidArgumentException
    {
        return new InvalidArgumentException("Payout amount must be greater than zero, got {$amount}.");
    }

    public static function invalidIban(string $iban): InvalidArgumentException
    {
        return new InvalidArgumentException("Target IBAN [{$iban}] does not look like a valid IBAN.");
    }
}
