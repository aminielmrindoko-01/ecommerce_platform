<?php

namespace App\Services;

use App\Events\OrderItemStatusChanged;
use App\Models\FulfillmentStatusHistory;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Controlled fulfillment state machine for order line items.
 *
 * Vendor and admin transition maps differ. Transitions use lockForUpdate()
 * and write fulfillment_status_histories on success.
 */
class OrderItemFulfillmentService
{
    /**
     * @var array<string, list<string>>
     */
    protected array $vendorTransitions = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    /**
     * Broader, explicitly defined admin overrides.
     *
     * @var array<string, list<string>>
     */
    protected array $adminTransitions = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => ['pending'],
    ];

    /**
     * @throws InvalidArgumentException
     */
    public function transition(
        OrderItem $item,
        string $nextStatus,
        ?User $actor = null,
        string $mode = 'vendor',
        ?string $reason = null,
    ): OrderItem {
        $nextStatus = strtolower(trim($nextStatus));
        $mode = $mode === 'admin' ? 'admin' : 'vendor';
        $reason = $reason !== null ? trim($reason) : null;
        if ($reason === '') {
            $reason = null;
        }

        if (! OrderItem::isValidFulfillmentStatus($nextStatus)) {
            throw new InvalidArgumentException('Invalid fulfillment status.');
        }

        return DB::transaction(function () use ($item, $nextStatus, $actor, $mode, $reason) {
            /** @var OrderItem $locked */
            $locked = OrderItem::query()
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $current = $locked->fulfillment_status ?: 'pending';

            if (! OrderItem::isValidFulfillmentStatus($current)) {
                throw new InvalidArgumentException('Current fulfillment status is invalid.');
            }

            if ($current === $nextStatus) {
                return $locked;
            }

            $allowed = $this->transitionsFor($mode)[$current] ?? [];

            if (! in_array($nextStatus, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Cannot transition fulfillment from {$current} to {$nextStatus}."
                );
            }

            if ($this->reasonRequired($mode, $current, $nextStatus) && blank($reason)) {
                throw new InvalidArgumentException('A reason is required for this fulfillment change.');
            }

            $locked->fulfillment_status = $nextStatus;
            $locked->save();

            $actorRole = $mode === 'admin'
                ? 'admin'
                : ($actor?->role ?? 'vendor');

            FulfillmentStatusHistory::create([
                'order_item_id' => $locked->id,
                'actor_user_id' => $actor?->id,
                'from_status' => $current,
                'to_status' => $nextStatus,
                'actor_role' => $actorRole,
                'reason' => $reason,
            ]);

            OrderItemStatusChanged::dispatch(
                $locked,
                $current,
                $nextStatus,
                $actor,
                $actorRole,
                $reason
            );

            return $locked->fresh(['product', 'order']);
        });
    }

    /**
     * @return list<string>
     */
    public function allowedTransitions(OrderItem $item, string $mode = 'vendor'): array
    {
        $current = $item->fulfillment_status ?: 'pending';

        return $this->transitionsFor($mode)[$current] ?? [];
    }

    public function canTransition(OrderItem $item, string $nextStatus, string $mode = 'vendor'): bool
    {
        $nextStatus = strtolower(trim($nextStatus));
        $current = $item->fulfillment_status ?: 'pending';

        if ($current === $nextStatus) {
            return true;
        }

        return in_array($nextStatus, $this->transitionsFor($mode)[$current] ?? [], true);
    }

    public function reasonRequired(string $mode, string $from, string $to): bool
    {
        if ($mode !== 'admin') {
            return false;
        }

        if ($from === 'cancelled' && $to === 'pending') {
            return true;
        }

        if ($to === 'cancelled' && in_array($from, ['processing', 'shipped'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function transitionsFor(string $mode): array
    {
        return $mode === 'admin' ? $this->adminTransitions : $this->vendorTransitions;
    }
}
