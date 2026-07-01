<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Listeners;

use Nizaamomer\LaravelFib\Events\Payments\PaymentCreated;
use Nizaamomer\LaravelFib\Events\Payments\PaymentRefundRequested;
use Nizaamomer\LaravelFib\Events\Payments\PaymentStatusUpdated;
use Nizaamomer\LaravelFib\Models\FibPayment;
use Nizaamomer\LaravelFib\Models\FibRefund;

final class PersistPaymentListener
{
    public function onCreated(PaymentCreated $event): void
    {
        FibPayment::query()->updateOrCreate(
            ['payment_id' => $event->payment->paymentId],
            [
                'account' => $event->account,
                'readable_code' => $event->payment->readableCode,
                'amount' => $event->amount,
                'currency' => $event->currency,
                'valid_until' => $event->payment->validUntil,
            ],
        );
    }

    public function onStatusUpdated(PaymentStatusUpdated $event): void
    {
        $status = $event->status;

        FibPayment::query()->updateOrCreate(
            ['payment_id' => $status->paymentId],
            [
                'account' => $event->account,
                'amount' => $status->amount,
                'currency' => $status->currency,
                'status' => $status->status,
                'declining_reason' => $status->decliningReason,
                'valid_until' => $status->validUntil,
                'paid_at' => $status->paidAt,
                'declined_at' => $status->declinedAt,
            ],
        );
    }

    public function onRefundRequested(PaymentRefundRequested $event): void
    {
        $refund = $event->refund;

        $payment = FibPayment::query()->where('payment_id', $refund->paymentId)->first();

        if ($payment === null) {
            return;
        }

        FibRefund::query()->updateOrCreate(
            ['payment_id' => $payment->id],
            [
                'fib_trace_id' => $refund->traceId,
                'status' => $refund->status,
                'error_codes' => $refund->errorCodes,
            ],
        );
    }
}
