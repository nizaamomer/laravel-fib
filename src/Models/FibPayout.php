<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nizaamomer\LaravelFib\Enums\Payouts\PayoutStatus;

/**
 * @property string $account
 * @property string $payout_id
 * @property string $target_account_iban
 * @property string|null $description
 * @property float $amount
 * @property string $currency
 * @property PayoutStatus $status
 * @property CarbonImmutable|null $authorized_at
 * @property CarbonImmutable|null $failed_at
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $meta
 */
class FibPayout extends Model
{
    protected $table = 'fib_payouts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount' => 'decimal:2',
            'authorized_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
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
}
