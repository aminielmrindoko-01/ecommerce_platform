<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\User;
use App\Services\Catalog\InventoryService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Couples order inventory settlement to verified payment outcomes.
 * Prevents double-commit / double-release via orders.inventory_state.
 */
class OrderInventorySettlement
{
    public const STATE_NONE = 'none';

    public const STATE_RESERVED = 'reserved';

    public const STATE_COMMITTED = 'committed';

    public const STATE_RELEASED = 'released';

    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function markReserved(Order $order): void
    {
        $order->forceFill(['inventory_state' => self::STATE_RESERVED])->save();
    }

    /**
     * Commit reserved stock after verified payment success (idempotent).
     */
    public function commitForPaidOrder(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $state = $locked->inventory_state ?: self::STATE_NONE;

            if ($state === self::STATE_COMMITTED) {
                return;
            }

            if ($state === self::STATE_RELEASED) {
                throw new InvalidArgumentException(
                    'Cannot commit inventory: reservation already released for this order.'
                );
            }

            if ($state !== self::STATE_RESERVED && $state !== self::STATE_NONE) {
                throw new InvalidArgumentException("Unexpected inventory state: {$state}");
            }

            // Legacy orders committed at checkout (Phase 4) may be STATE_NONE with
            // stock already sold — treat as already settled when no reserved qty remains.
            $locked->loadMissing('items.product');
            $hasReserved = false;
            foreach ($locked->items as $item) {
                if ($item->product && (int) ($item->product->reserved_quantity ?? 0) > 0) {
                    $hasReserved = true;
                    break;
                }
            }

            if ($state === self::STATE_NONE && ! $hasReserved) {
                $locked->forceFill(['inventory_state' => self::STATE_COMMITTED])->save();

                return;
            }

            foreach ($locked->items as $item) {
                if (! $item->product) {
                    continue;
                }
                $qty = (int) $item->quantity;
                if ($qty < 1) {
                    continue;
                }
                $this->inventory->commitSaleFromReserved(
                    $item->product->fresh(),
                    $qty,
                    $actor,
                    (string) $locked->id,
                );
                $item->product->increment('sold_count', $qty);
            }

            $locked->forceFill(['inventory_state' => self::STATE_COMMITTED])->save();
        });
    }

    /**
     * Release reservation after payment failure / expiry / unpaid cancel (idempotent).
     */
    public function releaseForUnpaidOrder(Order $order, ?User $actor = null, string $reason = 'Payment failed — reservation released'): void
    {
        DB::transaction(function () use ($order, $actor, $reason) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $state = $locked->inventory_state ?: self::STATE_NONE;

            if ($state === self::STATE_RELEASED) {
                return;
            }

            if ($state === self::STATE_COMMITTED) {
                // Paid stock — do not release via this path (use returns/refunds).
                return;
            }

            $locked->loadMissing('items.product');
            foreach ($locked->items as $item) {
                if (! $item->product) {
                    continue;
                }
                $qty = (int) $item->quantity;
                $reserved = (int) ($item->product->reserved_quantity ?? 0);
                if ($qty < 1 || $reserved < 1) {
                    continue;
                }
                $releaseQty = min($qty, $reserved);
                $this->inventory->releaseReserved(
                    $item->product->fresh(),
                    $releaseQty,
                    $actor,
                    (string) $locked->id,
                );
            }

            $locked->forceFill(['inventory_state' => self::STATE_RELEASED])->save();
        });
    }

    /**
     * Restock after cancelling a paid/committed order.
     */
    public function restockCommittedOrder(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (($locked->inventory_state ?: self::STATE_NONE) !== self::STATE_COMMITTED) {
                $this->releaseForUnpaidOrder($locked, $actor, 'Order cancelled — reservation released');

                return;
            }

            $locked->loadMissing('items.product');
            foreach ($locked->items as $item) {
                if (! $item->product) {
                    continue;
                }
                $qty = (int) $item->quantity;
                $this->inventory->adjust(
                    $item->product,
                    $qty,
                    'Order cancelled — stock restored',
                    $actor,
                    \App\Models\InventoryMovement::TYPE_RETURN,
                    'order',
                    (string) $locked->id,
                );
                $item->product->decrement('sold_count', min((int) $item->product->sold_count, $qty));
            }

            $locked->forceFill(['inventory_state' => self::STATE_RELEASED])->save();
        });
    }
}
