<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Exceptions;

use RuntimeException;

class FibAuthenticationException extends RuntimeException
{
    public static function tokenRequestFailed(int $status, string $body): self
    {
        return new self("FIB authentication failed with status {$status}: {$body}");
    }
}
