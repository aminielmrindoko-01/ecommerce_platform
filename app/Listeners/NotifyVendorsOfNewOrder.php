<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\VendorNewOrderReceived;

/**
 * Notify each distinct vendor once after a successful checkout.
 */
class NotifyVendorsOfNewOrder
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing('items.product.vendor.user');

        $byVendor = [];

        foreach ($order->items as $item) {
            $vendor = $item->product?->vendor;
            if (! $vendor || ! $vendor->user) {
                continue;
            }

            $vendorId = (int) $vendor->id;
            if (! isset($byVendor[$vendorId])) {
                $byVendor[$vendorId] = [
                    'user' => $vendor->user,
                    'count' => 0,
                ];
            }
            $byVendor[$vendorId]['count']++;
        }

        foreach ($byVendor as $entry) {
            $entry['user']->notify(new VendorNewOrderReceived($order, $entry['count']));
        }
    }
}
