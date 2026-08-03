<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEntitlement;
use App\Models\VendorPayout;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

/**
 * Vendor payable balances derived from the ledger (authoritative)
 * with entitlement hold awareness for available-to-withdraw.
 */
class VendorPayableService
{
    public function __construct(
        protected LedgerService $ledger,
        protected PaymentService $payments,
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
     *   pending_payouts:string
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
        $heldNet = '0.00';

        foreach ($ents as $ent) {
            $sales = bcadd($sales, $this->payments->normalizeMoney($ent->gross_amount), 2);
            $commission = bcadd($commission, $this->payments->normalizeMoney($ent->commission_amount), 2);
            $refundsNet = bcadd($refundsNet, $this->payments->normalizeMoney($ent->refunded_net), 2);
            $remaining = $ent->remainingNet();
            if ($ent->available_at && $ent->available_at->isFuture()) {
                $heldNet = bcadd($heldNet, $remaining, 2);
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

        // Available = ledger payable − open payouts − settlement holds.
        $available = bcsub($payableLedger, $pending, 2);
        $available = bcsub($available, $heldNet, 2);
        if (bccomp($available, '0.00', 2) < 0) {
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
        ];
    }

    public function availableBalance(Vendor $vendor): string
    {
        return $this->summaryForVendor($vendor)['available'];
    }
}
