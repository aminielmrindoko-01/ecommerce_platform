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

        // Platform staff with admin shell + orders.view (e.g. customer_support, auditor).
        if ($user->hasPermission('admin.access')) {
            return true;
        }

        // Customer owns the order.
        if ((int) $order->user_id === (int) $user->id) {
            return true;
        }

        // Vendor may view if they have a line item on the order (snapshot or live product).
        if ($user->isVendor() && $user->vendor) {
            $vendorId = (int) $user->vendor->id;

            return $order->items()
                ->where(function ($q) use ($vendorId) {
                    $q->where('vendor_id', $vendorId)
                        ->orWhereHas('product', fn ($pq) => $pq->where('vendor_id', $vendorId));
                })
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

    public function cancel(User $user, Order $order): bool
    {
        return app(\App\Services\Orders\OrderService::class)->actorMayCancel($order, $user);
    }
}
