<?php

namespace App\Listeners;

use App\Events\PaymentCancelled;
use App\Events\PaymentFailed;
use App\Events\PaymentSuccessful;
use App\Notifications\CustomerPaymentUpdated;

/**
 * Notify the order owner after payment outcome events (dispatched after commit).
 */
class SendPaymentNotifications
{
    public function handleSuccessful(PaymentSuccessful $event): void
    {
        $this->notify($event->paymentTransaction, 'successful');
    }

    public function handleFailed(PaymentFailed $event): void
    {
        $this->notify($event->paymentTransaction, 'failed');
    }

    public function handleCancelled(PaymentCancelled $event): void
    {
        $this->notify($event->paymentTransaction, 'cancelled');
    }

    protected function notify($paymentTransaction, string $outcome): void
    {
        $paymentTransaction->loadMissing('order.user');
        $customer = $paymentTransaction->order?->user;

        if ($customer) {
            $customer->notify(new CustomerPaymentUpdated($paymentTransaction, $outcome));
        }
    }
}
