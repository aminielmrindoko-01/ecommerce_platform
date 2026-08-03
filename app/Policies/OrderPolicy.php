<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Order visibility / mutation: permission + customer ownership or manage_any.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if (! $user->isActiveAccount() || ! $user->hasPermission('orders.view')) {
            return false;
        }

        if ($user->hasPermission('orders.manage_any')) {
            return true;
        }

        // Customer owns the order.
        if ((int) $order->user_id === (int) $user->id) {
            return true;
        }

        // Vendor may view if they have a line item on the order.
        if ($user->isVendor() && $user->vendor) {
            return $order->items()
                ->whereHas('product', fn ($q) => $q->where('vendor_id', $user->vendor->id))
                ->exists();
        }

        return false;
    }

    public function update(User $user, Order $order): bool
    {
        if (! $user->isActiveAccount() || ! $user->hasPermission('orders.update')) {
            return false;
        }

        return $user->hasPermission('orders.manage_any');
    }
}
