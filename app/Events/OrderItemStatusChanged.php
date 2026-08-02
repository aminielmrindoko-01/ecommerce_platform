<?php

namespace App\Events;

use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a successful fulfillment status change.
 *
 * Optional actor fields support notifications and audit-aware listeners.
 * Backward compatible with: dispatch($item, $from, $to)
 */
class OrderItemStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public OrderItem $orderItem,
        public string $previousStatus,
        public string $newStatus,
        public ?User $actor = null,
        public ?string $actorRole = null,
        public ?string $reason = null,
    ) {}
}
