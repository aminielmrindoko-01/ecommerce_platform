<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database notification when a vendor receives items in a new order.
 */
class VendorNewOrderReceived extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public int $itemCount,
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
        $orderNumber = $this->order->order_number ?? ('#'.$this->order->id);
        $count = $this->itemCount;

        return [
            'title' => "New order {$orderNumber}",
            'body' => "You received a new order containing {$count} product".($count === 1 ? '' : 's').' from your store.',
            'order_id' => $this->order->id,
            'item_count' => $count,
        ];
    }
}
