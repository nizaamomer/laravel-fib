<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Console\Commands;

use Illuminate\Console\Command;
use Nizaamomer\LaravelFib\Contracts\Payments\FibPaymentServiceContract;
use Nizaamomer\LaravelFib\Contracts\Payouts\FibPayoutServiceContract;
use Nizaamomer\LaravelFib\Enums\Payments\PaymentStatus;
use Nizaamomer\LaravelFib\Enums\Payouts\PayoutStatus;
use Nizaamomer\LaravelFib\Models\FibPayment;
use Nizaamomer\LaravelFib\Models\FibPayout;
use Throwable;

/**
 * Re-checks payments/payouts still in a pending state directly against FIB.
 *
 * A safety net for missed or delayed webhook callbacks — payouts have no
 * webhook at all, so this is the only way to learn a payout's final status
 * without the caller polling manually. Schedule it, e.g. in
 * bootstrap/app.php: ->withSchedule(fn ($schedule) =>
 * $schedule->command('fib:sync-statuses')->everyFiveMinutes())
 */
class SyncFibStatuses extends Command
{
    protected $signature = 'fib:sync-statuses {--limit=100}';

    protected $description = 'Re-check pending FIB payment and payout statuses directly from the FIB API';

    public function handle(FibPaymentServiceContract $payments, FibPayoutServiceContract $payouts): int
    {
        $limit = (int) $this->option('limit');

        $checkedPayments = $this->syncPayments($payments, $limit);
        $checkedPayouts = $this->syncPayouts($payouts, $limit);

        $this->info("Checked {$checkedPayments} pending payment(s) and {$checkedPayouts} pending payout(s).");

        return self::SUCCESS;
    }

    private function syncPayments(FibPaymentServiceContract $payments, int $limit): int
    {
        $pending = FibPayment::query()
            ->where('status', PaymentStatus::Unpaid)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->limit($limit)
            ->get();

        foreach ($pending as $payment) {
            try {
                $payments->status($payment->payment_id, $payment->account);
            } catch (Throwable $e) {
                $this->warn("Failed to sync payment {$payment->payment_id}: {$e->getMessage()}");
            }
        }

        return $pending->count();
    }

    private function syncPayouts(FibPayoutServiceContract $payouts, int $limit): int
    {
        $pending = FibPayout::query()
            ->where('status', PayoutStatus::Created)
            ->limit($limit)
            ->get();

        foreach ($pending as $payout) {
            try {
                $payouts->details($payout->payout_id, $payout->account);
            } catch (Throwable $e) {
                $this->warn("Failed to sync payout {$payout->payout_id}: {$e->getMessage()}");
            }
        }

        return $pending->count();
    }
}
