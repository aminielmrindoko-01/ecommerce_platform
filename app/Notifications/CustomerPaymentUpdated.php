<?php

namespace App\Notifications;

use App\Models\PaymentTransaction;
use App\Support\Payments\PaymentStatusPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database notification when a customer's order payment status changes.
 * Dispatched only after real PaymentService transitions (not stub init).
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
        $statusLabel = PaymentStatusPresenter::label($this->paymentTransaction->status);

        [$title, $body] = match ($this->outcome) {
            'successful' => [
                "Payment received — {$orderNumber}",
                "Payment received successfully for order {$orderNumber}.",
            ],
            'failed' => [
                "Payment failed — {$orderNumber}",
                "Payment for order {$orderNumber} failed.",
            ],
            'cancelled' => [
                "Payment cancelled — {$orderNumber}",
                "Payment for order {$orderNumber} was cancelled.",
            ],
            'pending' => [
                "Payment pending — {$orderNumber}",
                "Payment for order {$orderNumber} is still pending.",
            ],
            default => [
                "Payment update — {$orderNumber}",
                "Payment for order {$orderNumber} is now {$statusLabel}.",
            ],
        };

        return [
            'title' => $title,
            'body' => $body,
            'order_id' => $this->paymentTransaction->order_id,
            'payment_transaction_id' => $this->paymentTransaction->id,
            'payment_status' => $this->paymentTransaction->status,
            'reference' => $this->paymentTransaction->reference,
            'outcome' => $this->outcome,
        ];
    }
}
