<?php

namespace App\Services\Operations;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use InvalidArgumentException;

/**
 * Centralized return eligibility — never hard-code in controllers.
 */
class ReturnEligibilityService
{
    /**
     * @return array{ok:bool, reason:?string, max_qty:int}
     */
    public function evaluate(Order $order, OrderItem $item, User $customer, int $quantity): array
    {
        if ((int) $order->user_id !== (int) $customer->id) {
            return ['ok' => false, 'reason' => 'Order does not belong to this customer.', 'max_qty' => 0];
        }

        if ((int) $item->order_id !== (int) $order->id) {
            return ['ok' => false, 'reason' => 'Order item does not belong to this order.', 'max_qty' => 0];
        }

        $blocked = config('operations.returns.blocked_order_statuses', ['cancelled', 'pending']);
        if (in_array($order->status, $blocked, true)) {
            return ['ok' => false, 'reason' => 'Order status does not allow returns.', 'max_qty' => 0];
        }

        $eligibleStatuses = config('operations.returns.eligible_fulfillment_statuses', ['delivered']);
        $fulfillment = $item->fulfillment_status ?: 'pending';
        if (! in_array($fulfillment, $eligibleStatuses, true)) {
            return ['ok' => false, 'reason' => 'Item must be delivered before a return can be requested.', 'max_qty' => 0];
        }

        if (in_array($order->payment_status, ['pending', 'failed', 'cancelled', 'expired'], true)) {
            return ['ok' => false, 'reason' => 'Order is not paid.', 'max_qty' => 0];
        }

        $windowDays = (int) config('operations.returns.window_days', 14);
        $anchor = $item->updated_at ?: $order->updated_at ?: $order->created_at;
        if ($windowDays > 0 && $anchor && $anchor->lt(now()->subDays($windowDays))) {
            return ['ok' => false, 'reason' => "Return window of {$windowDays} days has expired.", 'max_qty' => 0];
        }

        $alreadyReturned = (int) ReturnItem::query()
            ->where('order_item_id', $item->id)
            ->whereHas('returnRequest', function ($q) {
                $q->whereNotIn('status', ['rejected', 'cancelled']);
            })
            ->sum('quantity');

        $maxQty = max(0, (int) $item->quantity - $alreadyReturned);
        if ($maxQty <= 0) {
            return ['ok' => false, 'reason' => 'No remaining quantity available to return.', 'max_qty' => 0];
        }

        if ($quantity < 1 || $quantity > $maxQty) {
            return ['ok' => false, 'reason' => "Return quantity must be between 1 and {$maxQty}.", 'max_qty' => $maxQty];
        }

        // Block if an open dispute exists on this item (ops should resolve dispute first).
        $openDispute = \App\Models\Dispute::query()
            ->where('order_item_id', $item->id)
            ->whereIn('status', \App\Models\Dispute::OPEN_STATUSES)
            ->exists();
        if ($openDispute) {
            return ['ok' => false, 'reason' => 'An open dispute exists for this item.', 'max_qty' => $maxQty];
        }

        return ['ok' => true, 'reason' => null, 'max_qty' => $maxQty];
    }

    public function assertEligible(Order $order, OrderItem $item, User $customer, int $quantity): void
    {
        $result = $this->evaluate($order, $item, $customer, $quantity);
        if (! $result['ok']) {
            throw new InvalidArgumentException($result['reason'] ?? 'Return not eligible.');
        }
    }
}
