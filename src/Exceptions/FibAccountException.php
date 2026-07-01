<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Exceptions;

use RuntimeException;

class FibAccountException extends RuntimeException
{
    public static function unknownAccount(string $account): self
    {
        return new self("FIB account [{$account}] is not configured in config/fib.php.");
    }
}
