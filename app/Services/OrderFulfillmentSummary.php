<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * Dynamically derive an order-level fulfillment summary from line items.
 *
 * Display-only — never stored; never replaces orders.status.
 */
class OrderFulfillmentSummary
{
    /**
     * @return string One of: pending, confirmed, partially_in_progress, processing,
     *                partially_shipped, shipped, partially_delivered, delivered,
     *                cancelled, mixed
     */
    public function summarize(Order $order): string
    {
        $order->loadMissing('items');

        $statuses = $order->items
            ->map(fn (OrderItem $item) => $item->fulfillment_status ?: 'pending')
            ->values();

        if ($statuses->isEmpty()) {
            return 'pending';
        }

        $unique = $statuses->unique()->values();
        $hasCancelled = $statuses->contains('cancelled');
        $active = $statuses->filter(fn ($s) => $s !== 'cancelled');

        if ($hasCancelled && $active->isNotEmpty()) {
            return 'mixed';
        }

        if ($unique->count() === 1) {
            return $unique->first();
        }

        if ($statuses->contains('delivered')) {
            return 'partially_delivered';
        }

        if ($statuses->contains('shipped')) {
            return 'partially_shipped';
        }

        if ($statuses->contains('processing') || $statuses->contains('confirmed')) {
            return 'partially_in_progress';
        }

        return 'mixed';
    }

    /**
     * Human label for UI.
     */
    public function label(string $summary): string
    {
        return match ($summary) {
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'partially_in_progress' => 'Partially in progress',
            'processing' => 'Processing',
            'partially_shipped' => 'Partially shipped',
            'shipped' => 'Shipped',
            'partially_delivered' => 'Partially delivered',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => 'Mixed',
        };
    }
}
