<?php

namespace App\Notifications;

use App\Models\PaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database notification when a customer's order payment status changes.
 */
class CustomerPaymentUpdated extends Notification
{
    use Queueable;

    public function __construct(
        public PaymentTransaction $paymentTransaction,
        public string $outcome,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $order = $this->paymentTransaction->order;
        $orderNumber = $order?->order_number ?? ('#'.$this->paymentTransaction->order_id);
        $reference = $this->paymentTransaction->reference;

        [$title, $body] = match ($this->outcome) {
            'successful' => [
                "Payment successful — {$orderNumber}",
                "Payment for order {$orderNumber} was successful (ref {$reference}).",
            ],
            'failed' => [
                "Payment failed — {$orderNumber}",
                "Payment for order {$orderNumber} failed (ref {$reference}).",
            ],
            'cancelled' => [
                "Payment cancelled — {$orderNumber}",
                "Payment for order {$orderNumber} was cancelled (ref {$reference}).",
            ],
            default => [
                "Payment update — {$orderNumber}",
                "Payment status updated for order {$orderNumber}.",
            ],
        };

        return [
            'title' => $title,
            'body' => $body,
            'order_id' => $this->paymentTransaction->order_id,
            'payment_transaction_id' => $this->paymentTransaction->id,
            'payment_status' => $this->paymentTransaction->status,
            'reference' => $reference,
            'outcome' => $this->outcome,
        ];
    }
}
