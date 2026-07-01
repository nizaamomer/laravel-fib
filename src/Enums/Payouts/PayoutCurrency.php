<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Enums\Payouts;

enum PayoutCurrency: string
{
    case IQD = 'IQD';
    case USD = 'USD';
    case EUR = 'EUR';
}
