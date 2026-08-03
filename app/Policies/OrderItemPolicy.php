<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;

/**
 * Order-item fulfillment: admin permission or owning vendor.
 */
class OrderItemPolicy
{
    public function view(User $user, OrderItem $orderItem): bool
    {
        if (! $user->isActiveAccount()) {
            return false;
        }

        if ($user->hasPermission('orders.manage_any') || $user->hasPermission('orders.view')) {
            if ($user->hasPermission('orders.manage_any')) {
                return true;
            }
        }

        return $this->owns($user, $orderItem);
    }

    public function updateFulfillment(User $user, OrderItem $orderItem): bool
    {
        if (! $user->isActiveAccount()) {
            return false;
        }

        if ($user->hasPermission('orders.manage_any') && $user->hasPermission('orders.update')) {
            return true;
        }

        return $user->hasPermission('orders.update') && $this->owns($user, $orderItem);
    }

    public function update(User $user, OrderItem $orderItem): bool
    {
        return $this->updateFulfillment($user, $orderItem);
    }

    protected function owns(User $user, OrderItem $orderItem): bool
    {
        if (! $user->isVendor() || ! $user->vendor) {
            return false;
        }

        $vendorId = (int) $user->vendor->id;
        if ($orderItem->vendor_id && (int) $orderItem->vendor_id === $vendorId) {
            return true;
        }

        $orderItem->loadMissing('product');

        return $orderItem->product
            && (int) $orderItem->product->vendor_id === $vendorId;
    }
}
