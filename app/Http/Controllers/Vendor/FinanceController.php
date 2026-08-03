<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorEntitlement;
use App\Models\VendorPayout;
use App\Services\Finance\PayoutService;
use App\Services\Finance\VendorPayableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Vendor-owned finance dashboard and payout requests.
 */
class FinanceController extends Controller
{
    public function index(VendorPayableService $payables): View
    {
        $vendor = auth()->user()->vendor;
        abort_unless($vendor, 403);

        $summary = $payables->summaryForVendor($vendor);
        $entitlements = VendorEntitlement::query()
            ->where('vendor_id', $vendor->id)
            ->with('order')
            ->latest('id')
            ->paginate(10);
        $payouts = VendorPayout::query()
            ->where('vendor_id', $vendor->id)
            ->latest('id')
            ->paginate(10, ['*'], 'payouts_page');

        return view('vendor.finance.index', compact('vendor', 'summary', 'entitlements', 'payouts'));
    }

    public function requestPayout(Request $request, PayoutService $payouts): RedirectResponse
    {
        $vendor = auth()->user()->vendor;
        abort_unless($vendor, 403);

        $data = $request->validate([
            'amount' => 'required|string|max:32',
            'idempotency_key' => 'nullable|string|max:128',
            'destination_token' => 'nullable|string|max:128',
        ]);

        foreach (['vendor_id', 'status', 'currency'] as $forbidden) {
            if ($request->exists($forbidden)) {
                abort(422, 'Invalid payout payload.');
            }
        }

        try {
            $payouts->request(
                $vendor,
                $data['amount'],
                $request->user(),
                $data['idempotency_key'] ?? $request->header('Idempotency-Key'),
                $data['destination_token'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout requested.');
    }
}
