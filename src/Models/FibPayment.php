<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nizaamomer\LaravelFib\Enums\Payments\DecliningReason;
use Nizaamomer\LaravelFib\Enums\Payments\PaymentStatus;

/**
 * @property string $account
 * @property string $payment_id
 * @property string|null $readable_code
 * @property float $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property DecliningReason|null $declining_reason
 * @property CarbonImmutable|null $valid_until
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $declined_at
 * @property array<string, mixed>|null $meta
 */
class FibPayment extends Model
{
    protected $table = 'fib_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'declining_reason' => DecliningReason::class,
            'amount' => 'decimal:2',
            'valid_until' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'declined_at' => 'immutable_datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasOne<FibRefund, $this>
     */
    public function refund(): HasOne
    {
        return $this->hasOne(FibRefund::class, 'payment_id');
    }
}
