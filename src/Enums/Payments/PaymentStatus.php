<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Enums\Payments;

enum PaymentStatus: string
{
    case Paid = 'PAID';
    case Unpaid = 'UNPAID';
    case Declined = 'DECLINED';
}
