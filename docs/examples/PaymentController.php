<?php

declare(strict_types=1);

/**
 * Full reference example for nizaamomer/laravel-fib.
 *
 * This is illustrative, not part of the package's autoload — copy what you
 * need into your own app. It assumes an App\Models\Order with a `total`
 * column and a `fibPayment()` MorphOne relation (see the README's
 * "Automatic persistence" section for how to add that relation).
 *
 * Covers every public method the package exposes: creating and checking a
 * payment, cancelling, refunding, the webhook callback, and the payout
 * create -> authorize -> details flow.
 */

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Nizaamomer\LaravelFib\Enums\Payments\PaymentCategory;
use Nizaamomer\LaravelFib\Facades\FibPayment;
use Nizaamomer\LaravelFib\Facades\FibPayout;
use Nizaamomer\LaravelFib\Models\FibPayment as FibPaymentModel;

class PaymentController extends Controller
{
    /**
     * Start a FIB payment for an order and hand the user a QR code / app
     * link to complete it.
     */
    public function pay(Order $order)
    {
        $payment = FibPayment::create(
            amount: $order->total,
            description: "Order #{$order->id}",
            // redirectUri, expiresIn and category are optional — shown here
            // for completeness, drop them if you don't need them.
            redirectUri: route('orders.show', $order),
            expiresIn: 'PT30M', // payment link expires after 30 minutes
            category: PaymentCategory::Ecommerce,
            // callbackUrl and currency are omitted on purpose — they fall
            // back to FIB_CALLBACK_URL and FIB_CURRENCY from your .env.
        );

        // The row in fib_payments already exists at this point (our
        // PaymentCreated event fired synchronously) — attach it to the order.
        FibPaymentModel::where('payment_id', $payment->paymentId)
            ->first()
            ?->payable()->associate($order)->save();

        // Also worth persisting on your own order row so you can look it up
        // without a join later.
        $order->update(['fib_payment_id' => $payment->paymentId]);

        return response()->json([
            'payment_id' => $payment->paymentId,
            'qr_code' => $payment->qrCode,
            'readable_code' => $payment->readableCode,
            'business_app_link' => $payment->businessAppLink,
            'personal_app_link' => $payment->personalAppLink,
            'valid_until' => $payment->validUntil, // CarbonImmutable, serializes to ISO-8601
        ]);
    }

    /**
     * Manual "check now" endpoint — e.g. a page the user lands on after
     * paying that polls this while waiting for the webhook.
     */
    public function checkStatus(Order $order)
    {
        $status = FibPayment::status($order->fib_payment_id);

        return response()->json([
            'status' => $status->status->value,  // PAID | UNPAID | DECLINED | REFUND_REQUESTED | REFUNDED
            'is_paid' => $status->isPaid(),
            'is_refundable' => $status->isRefundable(),
            'paid_at' => $status->paidAt,
        ]);
    }

    /**
     * Cancel an unpaid payment (e.g. the user abandoned checkout and you
     * want to free up the order for a new payment attempt).
     */
    public function cancel(Order $order)
    {
        FibPayment::cancel($order->fib_payment_id);

        $order->update(['status' => 'payment_cancelled']);

        return response()->noContent();
    }

    /**
     * Refund a paid order. Only works within the configured refundable
     * window (FIB_REFUNDABLE_FOR) and only for PAID payments.
     */
    public function refund(Order $order)
    {
        $status = FibPayment::status($order->fib_payment_id);

        if (! $status->isRefundable()) {
            return response()->json(['message' => 'This payment is outside its refundable window.'], 422);
        }

        $refund = FibPayment::refund($order->fib_payment_id);

        if (! $refund->isSuccessful()) {
            // $refund->traceId / $refund->errorCodes are useful to log and
            // to quote back to FIB support if the customer disputes it.
            return response()->json([
                'message' => 'Refund could not be processed.',
                'trace_id' => $refund->traceId,
                'error_codes' => $refund->errorCodes,
            ], 422);
        }

        // FIB moves the payment to REFUND_REQUESTED then REFUNDED shortly
        // after — this endpoint just confirms the request was accepted,
        // it doesn't mean the money has moved yet.
        $order->update(['status' => 'refund_requested']);

        return response()->json(['message' => 'Refund requested.']);
    }

    /**
     * FIB's webhook. Register this route WITHOUT CSRF protection and WITH
     * rate limiting — see the README for the exact route definition.
     *
     * Never trust $request->status directly: it isn't signed, so anyone who
     * learns this URL could POST a fake "PAID" body. Always re-fetch from
     * FIB before changing anything.
     */
    public function callback(Request $request)
    {
        $paymentId = $request->input('id');

        // Re-verify against FIB — this is the actual source of truth.
        $status = FibPayment::status($paymentId);

        $order = FibPaymentModel::where('payment_id', $paymentId)->first()?->payable;

        if ($order && $status->isPaid()) {
            $order->update(['status' => 'paid']);
        } elseif ($order && $status->status->value === 'DECLINED') {
            $order->update(['status' => 'payment_failed']);
        }

        // FIB expects 202 specifically, and retries up to 5 times if it
        // doesn't get a response at all — see the README's callback section.
        return response()->noContent(202);
    }

    /**
     * Pay out to a driver/vendor's IBAN. Two-step: create, then authorize.
     * Authorizing moves real money immediately — this example gates it
     * behind an explicit confirm flag as a bare-minimum safeguard; put your
     * own approval workflow here in a real app.
     */
    public function payout(Request $request)
    {
        $payout = FibPayout::create(
            amount: $request->float('amount'),
            targetAccountIban: $request->string('iban'),
            description: $request->string('description'),
            // currency omitted — falls back to FIB_CURRENCY automatically.
        );

        if ($request->boolean('confirm')) {
            FibPayout::authorize($payout->payoutId);
        }

        return response()->json(['payout_id' => $payout->payoutId]);
    }

    /**
     * Payouts have no webhook — poll details() yourself, or rely on the
     * package's `php artisan fib:sync-statuses` command (schedule it in
     * bootstrap/app.php) which does this automatically for every pending
     * payment and payout.
     */
    public function payoutStatus(string $payoutId)
    {
        $details = FibPayout::details($payoutId);

        return response()->json([
            'status' => $details->status->value, // CREATED | AUTHORIZED | FAILED
            'is_authorized' => $details->isAuthorized(),
            'is_failed' => $details->isFailed(),
            'failure_reason' => $details->failureReason,
        ]);
    }
}
