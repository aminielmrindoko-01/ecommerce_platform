<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\PesapalPaymentProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Browser return URL from PesaPal.
 *
 * Returning here is NOT proof of payment. Independent verification is required.
 */
class PesapalCallbackController extends Controller
{
    public function __invoke(Request $request, PesapalPaymentProcessor $processor): RedirectResponse
    {
        $payload = [
            'OrderTrackingId' => $request->query('OrderTrackingId'),
            'OrderMerchantReference' => $request->query('OrderMerchantReference'),
            'OrderNotificationType' => $request->query('OrderNotificationType', 'CALLBACKURL'),
        ];

        $result = $processor->processNotification($payload);
        $order = $result['order'];

        if (! $order) {
            return redirect()
                ->route('account.orders')
                ->with('error', 'We could not match that payment return to an order. No payment was assumed.');
        }

        if (auth()->check() && (int) $order->user_id !== (int) auth()->id() && ! auth()->user()?->isAdmin()) {
            abort(403);
        }

        if (! auth()->check()) {
            $tx = PaymentTransaction::query()->where('reference', $payload['OrderMerchantReference'])->first();

            return redirect()
                ->route('login')
                ->with('error', 'Please sign in to view your order. Returning from PesaPal does not mark payment as paid until verified.');
        }

        if ($order->payment_status === 'paid') {
            return redirect()
                ->route('checkout.confirmation', $order)
                ->with('success', 'Payment verified successfully for your order.');
        }

        return redirect()
            ->route('checkout.confirmation', $order)
            ->with('success', 'Returned from PesaPal. Payment status is still '.($order->payment_status ?: 'pending').'. Returning from the payment page is not proof of payment until independently verified.');
    }
}
