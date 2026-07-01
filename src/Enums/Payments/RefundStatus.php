<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Enums\Payments;

enum RefundStatus: string
{
    case Pending = 'PENDING';
    case Success = 'SUCCESS';
    case Failed = 'FAILED';
}
