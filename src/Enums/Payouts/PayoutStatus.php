<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Enums\Payouts;

enum PayoutStatus: string
{
    case Created = 'CREATED';
    case Authorized = 'AUTHORIZED';
    case Failed = 'FAILED';
}
