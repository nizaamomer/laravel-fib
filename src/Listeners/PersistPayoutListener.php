<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Listeners;

use Nizaamomer\LaravelFib\Events\Payouts\PayoutCreated;
use Nizaamomer\LaravelFib\Events\Payouts\PayoutStatusUpdated;
use Nizaamomer\LaravelFib\Models\FibPayout;

final class PersistPayoutListener
{
    public function onCreated(PayoutCreated $event): void
    {
        FibPayout::query()->updateOrCreate(
            ['payout_id' => $event->payout->payoutId],
            [
                'account' => $event->account,
                'target_account_iban' => $event->targetAccountIban,
                'description' => $event->description,
                'amount' => $event->amount,
                'currency' => $event->currency,
            ],
        );
    }

    public function onStatusUpdated(PayoutStatusUpdated $event): void
    {
        $status = $event->status;

        FibPayout::query()->updateOrCreate(
            ['payout_id' => $status->payoutId],
            [
                'account' => $event->account,
                'target_account_iban' => $status->targetAccountIban,
                'description' => $status->description,
                'amount' => $status->amount,
                'currency' => $status->currency->value,
                'status' => $status->status,
                'authorized_at' => $status->authorizedAt,
                'failed_at' => $status->failedAt,
                'failure_reason' => $status->failureReason,
            ],
        );
    }
}
