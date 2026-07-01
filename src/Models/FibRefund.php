<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nizaamomer\LaravelFib\Enums\Payments\RefundStatus;

/**
 * @property int $payment_id
 * @property string|null $fib_trace_id
 * @property RefundStatus $status
 * @property array<int, string>|null $error_codes
 */
class FibRefund extends Model
{
    protected $table = 'fib_refunds';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'error_codes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FibPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(FibPayment::class, 'payment_id');
    }
}
