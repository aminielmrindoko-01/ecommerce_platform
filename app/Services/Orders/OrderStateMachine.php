<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\Authorization\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Controlled order (header) lifecycle state machine.
 * Payment lifecycle remains on orders.payment_status via PaymentService.
 */
class OrderStateMachine
{
    /**
     * Canonical marketplace order statuses (compatible with legacy values).
     */
    public const STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'ready_for_fulfillment',
        'shipped',
        'delivered',
        'completed', // legacy synonym for delivered
        'cancelled',
        'refunded',
        'paid', // legacy only — not settable via OrderStateMachine (payment is separate)
    ];

    /**
     * Statuses operators may request via the order status endpoint.
     * `paid` is excluded — payment lifecycle is PaymentService only.
     *
     * @var list<string>
     */
    public const MUTABLE_STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'ready_for_fulfillment',
        'shipped',
        'delivered',
        'completed',
        'cancelled',
        'refunded',
    ];

    /**
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        'pending' => ['confirmed', 'cancelled'],
        'paid' => ['confirmed', 'processing', 'ready_for_fulfillment', 'shipped', 'cancelled'], // legacy
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready_for_fulfillment', 'shipped', 'cancelled'],
        'ready_for_fulfillment' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'completed'],
        'delivered' => ['refunded'],
        'completed' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function canTransition(string $from, string $to): bool
    {
        $from = $this->normalize($from);
        $to = $this->normalize($to);

        return in_array($to, $this->transitions[$from] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public function allowedNext(string $from): array
    {
        return $this->transitions[$this->normalize($from)] ?? [];
    }

    public function transition(Order $order, string $nextStatus, ?User $actor = null, ?string $reason = null): Order
    {
        $nextStatus = $this->normalize($nextStatus);
        if (! in_array($nextStatus, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid order status.');
        }

        return DB::transaction(function () use ($order, $nextStatus, $actor, $reason) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = $this->normalize($locked->status ?: 'pending');

            if ($current === $nextStatus) {
                return $locked;
            }

            if (! $this->canTransition($current, $nextStatus)) {
                throw new InvalidArgumentException("Cannot transition order from {$current} to {$nextStatus}.");
            }

            $locked->status = $nextStatus;
            $locked->save();

            $action = match ($nextStatus) {
                'confirmed' => 'ORDER_CONFIRMED',
                'processing' => 'ORDER_PROCESSING',
                'ready_for_fulfillment' => 'ORDER_READY_FOR_FULFILLMENT',
                'shipped' => 'ORDER_SHIPPED',
                'delivered', 'completed' => 'ORDER_DELIVERED',
                'cancelled' => 'ORDER_CANCELLED',
                'refunded' => 'ORDER_REFUNDED',
                default => 'ORDER_STATUS_CHANGED',
            };

            $this->audit->log(
                action: $action,
                actor: $actor,
                resourceType: 'order',
                resourceId: $locked->id,
                oldValues: ['status' => $current],
                newValues: ['status' => $nextStatus],
                reason: $reason,
            );

            return $locked->fresh();
        });
    }

    protected function normalize(string $status): string
    {
        return strtolower(trim($status));
    }
}
