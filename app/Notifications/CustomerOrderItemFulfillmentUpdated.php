<?php

namespace App\Notifications;

use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database notification for a customer when an order item status changes.
 */
class CustomerOrderItemFulfillmentUpdated extends Notification
{
    use Queueable;

    public function __construct(
        public OrderItem $orderItem,
        public string $previousStatus,
        public string $newStatus,
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
        $productName = $this->orderItem->product?->name ?? 'an item';
        $orderNumber = $this->orderItem->order?->order_number ?? ('#'.$this->orderItem->order_id);
        $status = ucfirst($this->newStatus);

        $body = match ($this->newStatus) {
            'confirmed' => "Your order item \"{$productName}\" has been confirmed.",
            'processing' => "Your order item \"{$productName}\" is now processing.",
            'shipped' => "Your order item \"{$productName}\" has shipped.",
            'delivered' => "Your order item \"{$productName}\" has been delivered.",
            'cancelled' => "Your order item \"{$productName}\" was cancelled.",
            default => "Your order item \"{$productName}\" is now {$status}.",
        };

        return [
            'title' => "Order {$orderNumber}: {$status}",
            'body' => $body,
            'order_id' => $this->orderItem->order_id,
            'order_item_id' => $this->orderItem->id,
            'fulfillment_status' => $this->newStatus,
            'previous_status' => $this->previousStatus,
        ];
    }
}
