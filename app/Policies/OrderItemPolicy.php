<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;

/**
 * Authorization for order-item fulfillment actions.
 *
 * Ownership: order_item → product.vendor_id → vendor.user_id === auth id.
 */
class OrderItemPolicy
{
    /**
     * Admin or owning vendor may view the item in a fulfillment context.
     */
    public function view(User $user, OrderItem $orderItem): bool
    {
        return $user->isAdmin() || $this->owns($user, $orderItem);
    }

    /**
     * Vendor may update fulfillment only for their own items; admin may also.
     */
    public function updateFulfillment(User $user, OrderItem $orderItem): bool
    {
        return $user->isAdmin() || $this->owns($user, $orderItem);
    }

    /**
     * Customers never change fulfillment via this policy.
     */
    public function update(User $user, OrderItem $orderItem): bool
    {
        return $this->updateFulfillment($user, $orderItem);
    }

    /**
     * Vendor ownership through product.vendor.user_id.
     */
    protected function owns(User $user, OrderItem $orderItem): bool
    {
        if (! $user->isVendor() || ! $user->vendor) {
            return false;
        }

        $orderItem->loadMissing('product');

        return $orderItem->product
            && (int) $orderItem->product->vendor_id === (int) $user->vendor->id;
    }
}
