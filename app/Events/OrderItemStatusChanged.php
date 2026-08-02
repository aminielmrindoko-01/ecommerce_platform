<?php

namespace App\Events;

use App\Models\OrderItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a successful fulfillment status change.
 *
 * No listeners yet — reserved for future customer/vendor notifications.
 */
class OrderItemStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public OrderItem $orderItem,
        public string $previousStatus,
        public string $newStatus,
    ) {}
}
