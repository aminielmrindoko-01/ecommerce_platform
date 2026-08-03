<?php

namespace App\Services\Operations;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEntitlement;
use App\Services\Authorization\AuditLogger;
use App\Services\Catalog\InventoryService;
use App\Services\PaymentService;
use App\Services\Payments\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Return lifecycle composed on top of RefundService + InventoryService + holds.
 */
class ReturnService
{
    /**
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        'requested' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['received', 'rejected', 'cancelled'],
        'received' => ['refunded'],
        'rejected' => [],
        'refunded' => [],
        'cancelled' => [],
    ];

    public function __construct(
        protected ReturnEligibilityService $eligibility,
        protected SettlementHoldService $holds,
        protected RefundService $refunds,
        protected InventoryService $inventory,
        protected PaymentService $payments,
        protected AuditLogger $audit,
    ) {}

    public function request(
        Order $order,
        OrderItem $item,
        User $customer,
        int $quantity,
        string $reason,
        ?string $reasonCode = null,
    ): ReturnRequest {
        $this->eligibility->assertEligible($order, $item, $customer, $quantity);

        $vendorId = $item->owningVendorId();
        if (! $vendorId) {
            throw new InvalidArgumentException('Order item has no vendor.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A return reason is required.');
        }

        return DB::transaction(function () use ($order, $item, $customer, $quantity, $reason, $reasonCode, $vendorId) {
            /** @var OrderItem $lockedItem */
            $lockedItem = OrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->eligibility->assertEligible($order->fresh(), $lockedItem, $customer, $quantity);

            $unit = $this->payments->normalizeMoney($lockedItem->getAttributes()['price'] ?? $lockedItem->price);
            $line = bcmul($unit, (string) $quantity, 2);
            $currency = $order->currency ?: config('finance.currency', 'TZS');

            $ret = new ReturnRequest;
            $ret->forceFill([
                'reference' => 'RTN-'.strtoupper(Str::random(10)),
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'vendor_id' => $vendorId,
                'status' => 'requested',
                'reason_code' => $reasonCode,
                'reason' => $reason,
                'requested_at' => now(),
            ])->save();

            $ri = new ReturnItem;
            $ri->forceFill([
                'return_request_id' => $ret->id,
                'order_item_id' => $lockedItem->id,
                'vendor_id' => $vendorId,
                'quantity' => $quantity,
                'unit_price' => $unit,
                'line_amount' => $line,
                'currency' => $currency,
            ])->save();

            if (config('operations.holds.auto_hold_on_return', true)) {
                $holdAmount = $this->holdAmountForReturn($vendorId, $lockedItem->id, $line);
                $hold = $this->holds->create(
                    vendor: Vendor::query()->findOrFail($vendorId),
                    amount: $holdAmount,
                    reasonCode: 'return',
                    actor: $customer,
                    orderId: $order->id,
                    orderItemId: $lockedItem->id,
                    sourceType: 'return_request',
                    sourceId: (string) $ret->id,
                    reason: 'Return request '.$ret->reference,
                );
                $ret->forceFill(['settlement_hold_id' => $hold->id])->save();
            }

            $this->audit->log(
                action: 'RETURN_REQUESTED',
                actor: $customer,
                resourceType: 'return_request',
                resourceId: $ret->id,
                newValues: [
                    'order_id' => $order->id,
                    'vendor_id' => $vendorId,
                    'order_item_id' => $lockedItem->id,
                    'quantity' => $quantity,
                    'amount' => $line,
                    'currency' => $currency,
                    'status' => 'requested',
                ],
            );

            return $ret->fresh(['items']);
        });
    }

    public function approve(ReturnRequest $return, User $actor): ReturnRequest
    {
        $this->assertCanManageVendorReturn($return, $actor);

        return $this->transition($return, 'approved', $actor, function (ReturnRequest $locked) use ($actor) {
            $locked->forceFill([
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ])->save();
        }, 'RETURN_APPROVED');
    }

    public function reject(ReturnRequest $return, User $actor, string $reason): ReturnRequest
    {
        $this->assertCanManageVendorReturn($return, $actor);
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Rejection reason is required.');
        }

        return $this->transition($return, 'rejected', $actor, function (ReturnRequest $locked) use ($actor, $reason) {
            $locked->forceFill([
                'rejection_reason' => $reason,
                'closed_at' => now(),
            ])->save();
            if ($locked->settlement_hold_id) {
                $hold = $locked->settlementHold;
                if ($hold && $hold->isActive()) {
                    $this->holds->release($hold, $actor, 'Return rejected');
                }
            }
        }, 'RETURN_REJECTED');
    }

    public function cancel(ReturnRequest $return, User $actor): ReturnRequest
    {
        if ((int) $return->customer_id !== (int) $actor->id
            && ! $actor->hasPermission('returns.manage')) {
            throw new InvalidArgumentException('You cannot cancel this return.');
        }

        return $this->transition($return, 'cancelled', $actor, function (ReturnRequest $locked) use ($actor) {
            $locked->forceFill(['closed_at' => now()])->save();
            if ($locked->settlement_hold_id) {
                $hold = $locked->settlementHold;
                if ($hold && $hold->isActive()) {
                    $this->holds->release($hold, $actor, 'Return cancelled');
                }
            }
        }, 'RETURN_REJECTED');
    }

    public function markReceived(ReturnRequest $return, User $actor, bool $restockable = true): ReturnRequest
    {
        $this->assertCanManageVendorReturn($return, $actor);

        return $this->transition($return, 'received', $actor, function (ReturnRequest $locked) use ($actor, $restockable) {
            $locked->load('items.orderItem');
            $locked->forceFill([
                'received_at' => now(),
                'received_by' => $actor->id,
            ])->save();

            foreach ($locked->items as $ri) {
                $ri->forceFill(['restockable' => $restockable])->save();
                if ($restockable) {
                    $productId = $ri->orderItem?->product_id;
                    if ($productId) {
                        $product = Product::query()->find($productId);
                        if ($product) {
                            $this->inventory->adjust(
                                $product,
                                (int) $ri->quantity,
                                'Return restock '.$locked->reference,
                                $actor,
                                InventoryMovement::TYPE_RETURN,
                                'return_request',
                                (string) $locked->id,
                            );
                        }
                    }
                }
            }

            $locked->forceFill(['restocked' => $restockable])->save();

            $this->audit->log(
                action: 'RETURN_RECEIVED',
                actor: $actor,
                resourceType: 'return_request',
                resourceId: $locked->id,
                oldValues: ['status' => 'approved'],
                newValues: [
                    'status' => 'received',
                    'restockable' => $restockable,
                    'vendor_id' => $locked->vendor_id,
                    'order_id' => $locked->order_id,
                ],
            );
        }, null);
    }

    /**
     * Issue refund via existing RefundService after item received.
     */
    public function processRefund(ReturnRequest $return, User $actor, string $reason = 'Return refund'): ReturnRequest
    {
        if (! $actor->hasPermission('refunds.create') && ! $actor->hasPermission('orders.refund')) {
            throw new InvalidArgumentException('Missing refund permission.');
        }

        return DB::transaction(function () use ($return, $actor, $reason) {
            /** @var ReturnRequest $locked */
            $locked = ReturnRequest::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($locked->status, 'refunded');

            if ($locked->payment_refund_id) {
                return $locked->fresh(['items', 'paymentRefund']);
            }

            $locked->load('items');
            $amount = $locked->refundAmount();
            $order = Order::query()->findOrFail($locked->order_id);

            $itemIds = $locked->items->pluck('order_item_id')->all();
            $refund = $this->refunds->refund(
                $order,
                $amount,
                $actor,
                $reason,
                null,
                $locked->id,
                [
                    'return_request_id' => $locked->id,
                    'order_item_ids' => $itemIds,
                    'allocation' => 'return_items',
                ],
            );

            $locked->forceFill([
                'status' => 'refunded',
                'payment_refund_id' => $refund->id,
                'refunded_at' => now(),
                'closed_at' => now(),
            ])->save();

            if ($locked->settlement_hold_id) {
                $hold = $locked->settlementHold;
                if ($hold && $hold->isActive()) {
                    $this->holds->release($hold, $actor, 'Return refunded');
                }
            }

            $this->audit->log(
                action: 'RETURN_REFUNDED',
                actor: $actor,
                resourceType: 'return_request',
                resourceId: $locked->id,
                oldValues: ['status' => 'received'],
                newValues: [
                    'status' => 'refunded',
                    'refund_id' => $refund->id,
                    'amount' => $amount,
                    'currency' => $order->currency ?: 'TZS',
                    'order_id' => $order->id,
                    'vendor_id' => $locked->vendor_id,
                ],
            );

            return $locked->fresh(['items', 'paymentRefund']);
        });
    }

    protected function transition(
        ReturnRequest $return,
        string $to,
        User $actor,
        ?callable $mutator = null,
        ?string $auditAction = null,
    ): ReturnRequest {
        return DB::transaction(function () use ($return, $to, $actor, $mutator, $auditAction) {
            /** @var ReturnRequest $locked */
            $locked = ReturnRequest::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $this->assertTransition($from, $to);
            $locked->status = $to;
            $locked->save();
            if ($mutator) {
                $mutator($locked);
            }

            if ($auditAction) {
                $this->audit->log(
                    action: $auditAction,
                    actor: $actor,
                    resourceType: 'return_request',
                    resourceId: $locked->id,
                    oldValues: ['status' => $from],
                    newValues: [
                        'status' => $to,
                        'vendor_id' => $locked->vendor_id,
                        'order_id' => $locked->order_id,
                        'reason' => $to === 'cancelled' ? 'cancelled_by_actor' : null,
                    ],
                );
            }

            return $locked->fresh(['items']);
        });
    }

    protected function assertTransition(string $from, string $to): void
    {
        $allowed = $this->transitions[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("Invalid return transition {$from} → {$to}.");
        }
    }

    protected function assertCanManageVendorReturn(ReturnRequest $return, User $actor): void
    {
        if ($actor->hasPermission('returns.manage') || $actor->hasPermission('returns.approve')) {
            return;
        }

        $vendorId = (int) ($actor->vendor?->id ?? 0);
        if ($vendorId > 0 && $vendorId === (int) $return->vendor_id) {
            return;
        }

        throw new InvalidArgumentException('Not authorized to manage this return.');
    }

    protected function holdAmountForReturn(int $vendorId, int $orderItemId, string $grossLine): string
    {
        $ent = VendorEntitlement::query()
            ->where('order_item_id', $orderItemId)
            ->where('vendor_id', $vendorId)
            ->first();

        if ($ent) {
            // Hold remaining net for this entitlement (or proportional to returned gross share).
            $gross = $this->payments->normalizeMoney($ent->gross_amount);
            $remainingNet = $ent->remainingNet();
            if (bccomp($gross, '0.00', 2) <= 0) {
                return $remainingNet;
            }
            $share = bcdiv($grossLine, $gross, 4);
            $hold = bcmul($remainingNet, $share, 2);

            return bccomp($hold, '0.00', 2) > 0 ? $hold : '0.01';
        }

        return $grossLine;
    }
}
