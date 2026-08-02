<?php

namespace App\Notifications;

use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database notification when a vendor's order item is cancelled.
 */
class VendorOrderItemCancelled extends Notification
{
    use Queueable;

    public function __construct(public OrderItem $orderItem) {}

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
        $productName = $this->orderItem->product?->name ?? 'An item';
        $orderNumber = $this->orderItem->order?->order_number ?? ('#'.$this->orderItem->order_id);

        return [
            'title' => "Order {$orderNumber}: item cancelled",
            'body' => "An order item from your store was cancelled ({$productName}).",
            'order_id' => $this->orderItem->order_id,
            'order_item_id' => $this->orderItem->id,
            'fulfillment_status' => 'cancelled',
        ];
    }
}
