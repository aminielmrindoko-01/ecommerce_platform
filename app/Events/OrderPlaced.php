<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after checkout successfully commits an order.
 *
 * Used to notify each distinct vendor once about their items.
 */
class OrderPlaced
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Order $order) {}
}
