<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Contracts;

interface FibAuthServiceContract
{
    /**
     * Get a valid (cached) bearer token for the given account.
     */
    public function token(string $account): string;

    /**
     * Force-refresh the cached token for the given account.
     */
    public function refreshToken(string $account): string;
}
