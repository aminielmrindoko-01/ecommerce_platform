<?php

namespace App\Services\Operations;

use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEntitlement;
use App\Services\Authorization\AuditLogger;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DisputeService
{
    /**
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        'open' => ['under_review', 'waiting_customer', 'waiting_vendor', 'resolved_customer', 'resolved_vendor', 'partially_resolved', 'closed'],
        'under_review' => ['waiting_customer', 'waiting_vendor', 'resolved_customer', 'resolved_vendor', 'partially_resolved', 'closed'],
        'waiting_customer' => ['under_review', 'waiting_vendor', 'resolved_customer', 'resolved_vendor', 'partially_resolved', 'closed'],
        'waiting_vendor' => ['under_review', 'waiting_customer', 'resolved_customer', 'resolved_vendor', 'partially_resolved', 'closed'],
        'resolved_customer' => ['closed'],
        'resolved_vendor' => ['closed'],
        'partially_resolved' => ['closed'],
        'closed' => [],
    ];

    public function __construct(
        protected SettlementHoldService $holds,
        protected PaymentService $payments,
        protected AuditLogger $audit,
    ) {}

    public function open(
        Order $order,
        User $customer,
        string $subject,
        string $description,
        ?OrderItem $item = null,
    ): Dispute {
        if ((int) $order->user_id !== (int) $customer->id) {
            throw new InvalidArgumentException('Order does not belong to this customer.');
        }

        $subject = trim($subject);
        $description = trim($description);
        if ($subject === '' || strlen($subject) > 160) {
            throw new InvalidArgumentException('Dispute subject is required (max 160 characters).');
        }
        if ($description === '') {
            throw new InvalidArgumentException('Dispute description is required.');
        }

        $vendorId = null;
        if ($item) {
            if ((int) $item->order_id !== (int) $order->id) {
                throw new InvalidArgumentException('Order item does not belong to this order.');
            }
            $vendorId = $item->owningVendorId();
        } else {
            $vendorId = $order->items->first()?->owningVendorId();
        }
        if (! $vendorId) {
            throw new InvalidArgumentException('Unable to determine vendor for dispute.');
        }

        return DB::transaction(function () use ($order, $customer, $subject, $description, $item, $vendorId) {
            if ($item) {
                $dup = Dispute::query()
                    ->where('order_item_id', $item->id)
                    ->whereIn('status', Dispute::OPEN_STATUSES)
                    ->lockForUpdate()
                    ->exists();
                if ($dup) {
                    throw new InvalidArgumentException('An open dispute already exists for this item.');
                }
            }

            $dispute = new Dispute;
            $dispute->forceFill([
                'reference' => 'DSP-'.strtoupper(Str::random(10)),
                'order_id' => $order->id,
                'order_item_id' => $item?->id,
                'customer_id' => $customer->id,
                'vendor_id' => $vendorId,
                'status' => 'open',
                'subject' => $subject,
                'description' => $description,
                'opened_at' => now(),
            ])->save();

            $msg = new DisputeMessage;
            $msg->forceFill([
                'dispute_id' => $dispute->id,
                'author_id' => $customer->id,
                'author_role' => 'customer',
                'body' => $description,
            ])->save();

            if (config('operations.holds.auto_hold_on_dispute', true)) {
                $holdAmount = $this->holdAmount($vendorId, $item?->id, $order->id);
                $hold = $this->holds->create(
                    vendor: Vendor::query()->findOrFail($vendorId),
                    amount: $holdAmount,
                    reasonCode: 'dispute',
                    actor: $customer,
                    orderId: $order->id,
                    orderItemId: $item?->id,
                    sourceType: 'dispute',
                    sourceId: (string) $dispute->id,
                    reason: 'Dispute '.$dispute->reference,
                );
                $dispute->forceFill(['settlement_hold_id' => $hold->id])->save();
            }

            $this->audit->log(
                action: 'DISPUTE_OPENED',
                actor: $customer,
                resourceType: 'dispute',
                resourceId: $dispute->id,
                newValues: [
                    'order_id' => $order->id,
                    'vendor_id' => $vendorId,
                    'order_item_id' => $item?->id,
                    'status' => 'open',
                    'subject' => $subject,
                ],
            );

            return $dispute->fresh(['messages']);
        });
    }

    public function respond(Dispute $dispute, User $actor, string $body, ?string $evidenceRef = null): DisputeMessage
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Message body is required.');
        }

        $role = $this->assertCanAccess($dispute, $actor, write: true);

        return DB::transaction(function () use ($dispute, $actor, $body, $evidenceRef, $role) {
            /** @var Dispute $locked */
            $locked = Dispute::query()->whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpen() && ! in_array($locked->status, ['partially_resolved'], true)) {
                throw new InvalidArgumentException('Dispute is closed.');
            }

            $msg = new DisputeMessage;
            $msg->forceFill([
                'dispute_id' => $locked->id,
                'author_id' => $actor->id,
                'author_role' => $role,
                'body' => $body,
                'evidence_ref' => $evidenceRef ? Str::limit($evidenceRef, 255, '') : null,
            ])->save();

            if ($locked->status === 'open') {
                $locked->forceFill(['status' => 'under_review'])->save();
            } elseif ($role === 'vendor' && $locked->status !== 'waiting_customer') {
                $locked->forceFill(['status' => 'waiting_customer'])->save();
            } elseif ($role === 'customer' && $locked->status !== 'waiting_vendor') {
                $locked->forceFill(['status' => 'waiting_vendor'])->save();
            }

            return $msg;
        });
    }

    public function resolve(Dispute $dispute, User $actor, string $resolutionStatus, ?string $notes = null): Dispute
    {
        if (! $actor->hasPermission('disputes.resolve') && ! $actor->hasPermission('disputes.manage')) {
            throw new InvalidArgumentException('Missing dispute resolve permission.');
        }

        $allowed = ['resolved_customer', 'resolved_vendor', 'partially_resolved', 'closed'];
        if (! in_array($resolutionStatus, $allowed, true)) {
            throw new InvalidArgumentException('Invalid resolution status.');
        }

        return DB::transaction(function () use ($dispute, $actor, $resolutionStatus, $notes) {
            /** @var Dispute $locked */
            $locked = Dispute::query()->whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $allowedTo = $this->transitions[$from] ?? [];
            if (! in_array($resolutionStatus, $allowedTo, true)) {
                throw new InvalidArgumentException("Invalid dispute transition {$from} → {$resolutionStatus}.");
            }

            $locked->forceFill([
                'status' => $resolutionStatus,
                'resolution_notes' => $notes,
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'closed_at' => $resolutionStatus === 'closed' ? now() : $locked->closed_at,
            ])->save();

            if (in_array($resolutionStatus, ['resolved_customer', 'resolved_vendor', 'partially_resolved', 'closed'], true)
                && $locked->settlement_hold_id) {
                $hold = $locked->settlementHold;
                // Keep hold on customer-favoring resolution until refund/chargeback settles;
                // release when vendor wins or closed without financial action.
                if ($hold && $hold->isActive() && in_array($resolutionStatus, ['resolved_vendor', 'closed'], true)) {
                    $this->holds->release($hold, $actor, 'Dispute '.$resolutionStatus);
                }
            }

            $this->audit->log(
                action: $resolutionStatus === 'closed' ? 'DISPUTE_CLOSED' : 'DISPUTE_RESOLVED',
                actor: $actor,
                resourceType: 'dispute',
                resourceId: $locked->id,
                oldValues: ['status' => $from],
                newValues: [
                    'status' => $resolutionStatus,
                    'vendor_id' => $locked->vendor_id,
                    'order_id' => $locked->order_id,
                    'reason' => $notes,
                ],
            );

            return $locked->fresh(['messages']);
        });
    }

    public function assertCanAccess(Dispute $dispute, User $actor, bool $write = false): string
    {
        if ($actor->hasPermission('disputes.manage') || $actor->hasPermission('disputes.resolve')
            || $actor->hasPermission('disputes.view')) {
            if ($write && ! $actor->hasPermission('disputes.manage') && ! $actor->hasPermission('disputes.resolve')
                && ! $actor->hasPermission('disputes.respond')) {
                // support with view can still respond if they have respond OR manage
            }
            if ($actor->hasPermission('disputes.manage') || $actor->hasPermission('disputes.resolve')) {
                return 'support';
            }
            if ($actor->hasPermission('disputes.respond') || $actor->hasPermission('disputes.view')) {
                return 'support';
            }
        }

        if ((int) $dispute->customer_id === (int) $actor->id) {
            return 'customer';
        }

        $vendorId = (int) ($actor->vendor?->id ?? 0);
        if ($vendorId > 0 && $vendorId === (int) $dispute->vendor_id) {
            return 'vendor';
        }

        throw new InvalidArgumentException('Not authorized to access this dispute.');
    }

    protected function holdAmount(int $vendorId, ?int $orderItemId, int $orderId): string
    {
        if ($orderItemId) {
            $ent = VendorEntitlement::query()
                ->where('order_item_id', $orderItemId)
                ->where('vendor_id', $vendorId)
                ->first();
            if ($ent) {
                $remaining = $ent->remainingNet();

                return bccomp($remaining, '0.00', 2) > 0 ? $remaining : '0.01';
            }
        }

        $sum = VendorEntitlement::query()
            ->where('order_id', $orderId)
            ->where('vendor_id', $vendorId)
            ->get()
            ->reduce(fn ($c, $e) => bcadd($c, $e->remainingNet(), 2), '0.00');

        return bccomp($sum, '0.00', 2) > 0 ? $sum : '0.01';
    }
}
