<?php

namespace App\Listeners;

use App\Events\OrderItemStatusChanged;
use App\Notifications\CustomerOrderItemFulfillmentUpdated;
use App\Notifications\VendorOrderItemCancelled;

/**
 * Notify customer (and vendor on cancel) after a fulfillment change.
 */
class SendFulfillmentNotifications
{
    public function handle(OrderItemStatusChanged $event): void
    {
        $item = $event->orderItem->loadMissing(['order.user', 'product.vendor.user']);

        $customer = $item->order?->user;
        if ($customer) {
            $customer->notify(new CustomerOrderItemFulfillmentUpdated(
                $item,
                $event->previousStatus,
                $event->newStatus
            ));
        }

        if ($event->newStatus === 'cancelled') {
            $vendorUser = $item->product?->vendor?->user;
            if ($vendorUser) {
                $vendorUser->notify(new VendorOrderItemCancelled($item));
            }
        }
    }
}
