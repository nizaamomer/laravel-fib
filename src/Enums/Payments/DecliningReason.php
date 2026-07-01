<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Enums\Payments;

enum DecliningReason: string
{
    case ServerFailure = 'SERVER_FAILURE';
    case PaymentExpiration = 'PAYMENT_EXPIRATION';
    case PaymentCancellation = 'PAYMENT_CANCELLATION';
}
