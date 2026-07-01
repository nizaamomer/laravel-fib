<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Enums\Payments;

enum PaymentCategory: string
{
    case Erp = 'ERP';
    case Pos = 'POS';
    case Ecommerce = 'ECOMMERCE';
    case Utility = 'UTILITY';
    case Payroll = 'PAYROLL';
    case Supplier = 'SUPPLIER';
    case Loan = 'LOAN';
    case Government = 'GOVERNMENT';
    case Miscellaneous = 'MISCELLANEOUS';
    case Other = 'OTHER';
}
