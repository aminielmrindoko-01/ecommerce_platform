<?php

namespace App\Services\Finance;

use App\Models\Vendor;
use App\Models\VendorEntitlement;
use App\Models\VendorPayout;
use App\Services\Operations\SettlementHoldService;
use App\Services\PaymentService;

/**
 * Vendor payable balances derived from the ledger (authoritative)
 * with entitlement hold + explicit settlement_holds awareness.
 */
class VendorPayableService
{
    public function __construct(
        protected LedgerService $ledger,
        protected PaymentService $payments,
        protected SettlementHoldService $holds,
    ) {}

    /**
     * @return array{
     *   currency:string,
     *   sales_gross:string,
     *   commission:string,
     *   refunds_net:string,
     *   payable_ledger:string,
     *   paid_out:string,
     *   available:string,
     *   pending_payouts:string,
     *   settlement_holds:string,
     *   entitlement_holds:string,
     *   financial_status:string
     * }
     */
    public function summaryForVendor(Vendor $vendor): array
    {
        $currency = config('finance.currency', 'TZS');
        $vendorId = (int) $vendor->id;

        $ents = VendorEntitlement::query()->where('vendor_id', $vendorId)->get();
        $sales = '0.00';
        $commission = '0.00';
        $refundsNet = '0.00';
        $entitlementHeld = '0.00';

        foreach ($ents as $ent) {
            $sales = bcadd($sales, $this->payments->normalizeMoney($ent->gross_amount), 2);
            $commission = bcadd($commission, $this->payments->normalizeMoney($ent->commission_amount), 2);
            $refundsNet = bcadd($refundsNet, $this->payments->normalizeMoney($ent->refunded_net), 2);
            $remaining = $ent->remainingNet();
            if ($ent->available_at && $ent->available_at->isFuture()) {
                $entitlementHeld = bcadd($entitlementHeld, $remaining, 2);
            }
        }

        $payableLedger = $this->ledger->vendorPayableBalance($vendorId, $currency);

        $paidOut = VendorPayout::query()
            ->where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->sum('amount');
        $paidOut = $this->payments->normalizeMoney($paidOut ?: '0');

        $pending = VendorPayout::query()
            ->where('vendor_id', $vendorId)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('amount');
        $pending = $this->payments->normalizeMoney($pending ?: '0');

        $explicitHolds = $this->holds->activeHeldAmount($vendorId);

        // Available = ledger payable − open payouts − entitlement settlement period − explicit holds.
        $available = bcsub($payableLedger, $pending, 2);
        $available = bcsub($available, $entitlementHeld, 2);
        $available = bcsub($available, $explicitHolds, 2);
        if (bccomp($available, '0.00', 2) < 0) {
            $available = '0.00';
        }

        if (! $vendor->canRequestPayout()) {
            $available = '0.00';
        }

        return [
            'currency' => $currency,
            'sales_gross' => $sales,
            'commission' => $commission,
            'refunds_net' => $refundsNet,
            'payable_ledger' => $payableLedger,
            'paid_out' => $paidOut,
            'available' => $available,
            'pending_payouts' => $pending,
            'settlement_holds' => $explicitHolds,
            'entitlement_holds' => $entitlementHeld,
            'financial_status' => $vendor->financialStatus(),
        ];
    }

    public function availableBalance(Vendor $vendor): string
    {
        return $this->summaryForVendor($vendor)['available'];
    }
}
