<?php

namespace App\Services;

use App\Events\OrderItemStatusChanged;
use App\Models\OrderItem;
use InvalidArgumentException;

/**
 * Controlled fulfillment state machine for order line items.
 *
 * Vendor transitions are limited; illegal jumps throw InvalidArgumentException.
 */
class OrderItemFulfillmentService
{
    /**
     * Allowed next statuses keyed by current status.
     *
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    /**
     * Transition an order item to a new fulfillment status.
     *
     * @throws InvalidArgumentException When status or transition is invalid
     */
    public function transition(OrderItem $item, string $nextStatus): OrderItem
    {
        $nextStatus = strtolower(trim($nextStatus));

        if (! OrderItem::isValidFulfillmentStatus($nextStatus)) {
            throw new InvalidArgumentException('Invalid fulfillment status.');
        }

        $current = $item->fulfillment_status ?: 'pending';

        if (! OrderItem::isValidFulfillmentStatus($current)) {
            throw new InvalidArgumentException('Current fulfillment status is invalid.');
        }

        if ($current === $nextStatus) {
            return $item;
        }

        $allowed = $this->transitions[$current] ?? [];

        if (! in_array($nextStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot transition fulfillment from {$current} to {$nextStatus}."
            );
        }

        $item->fulfillment_status = $nextStatus;
        $item->save();

        OrderItemStatusChanged::dispatch($item, $current, $nextStatus);

        return $item->fresh(['product', 'order']);
    }

    /**
     * Next statuses a vendor may choose from the current state.
     *
     * @return list<string>
     */
    public function allowedTransitions(OrderItem $item): array
    {
        $current = $item->fulfillment_status ?: 'pending';

        return $this->transitions[$current] ?? [];
    }

    /**
     * Whether a transition is legal (does not persist).
     */
    public function canTransition(OrderItem $item, string $nextStatus): bool
    {
        $nextStatus = strtolower(trim($nextStatus));
        $current = $item->fulfillment_status ?: 'pending';

        if ($current === $nextStatus) {
            return true;
        }

        return in_array($nextStatus, $this->transitions[$current] ?? [], true);
    }
}
