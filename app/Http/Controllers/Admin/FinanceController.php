<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use App\Models\Vendor;
use App\Models\VendorEntitlement;
use App\Models\VendorPayout;
use App\Services\Finance\PayoutService;
use App\Services\Finance\VendorPayableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Admin finance: ledger, payables, payouts.
 */
class FinanceController extends Controller
{
    public function ledger(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('ledger.view'), 403);

        $transactions = LedgerTransaction::query()
            ->with(['entries.account', 'order', 'vendor'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.finance.ledger', compact('transactions'));
    }

    public function payables(Request $request, VendorPayableService $payables): View
    {
        abort_unless(
            $request->user()?->hasPermission('payouts.view')
            || $request->user()?->hasPermission('finance.reports.view'),
            403
        );

        $vendors = Vendor::query()->orderBy('store_name')->paginate(20);
        $summaries = [];
        foreach ($vendors as $vendor) {
            $summaries[$vendor->id] = $payables->summaryForVendor($vendor);
        }

        return view('admin.finance.payables', compact('vendors', 'summaries'));
    }

    public function payouts(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('payouts.view'), 403);

        $query = VendorPayout::query()->with(['vendor', 'requester', 'approver'])->latest('id');
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $payouts = $query->paginate(20)->withQueryString();

        return view('admin.finance.payouts', compact('payouts'));
    }

    public function approvePayout(Request $request, VendorPayout $payout, PayoutService $payouts): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasPermission('payouts.approve')
            || $request->user()?->hasPermission('payouts.process'),
            403
        );

        try {
            $payouts->approve($payout, $request->user(), $request->input('reason'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout approved.');
    }

    public function processPayout(Request $request, VendorPayout $payout, PayoutService $payouts): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('payouts.process'), 403);

        try {
            $payouts->process($payout, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout processed.');
    }

    public function rejectPayout(Request $request, VendorPayout $payout, PayoutService $payouts): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasPermission('payouts.reject')
            || $request->user()?->hasPermission('payouts.approve'),
            403
        );

        $data = $request->validate(['reason' => 'required|string|max:500']);

        try {
            $payouts->reject($payout, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout rejected.');
    }

    public function entitlements(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('finance.reports.view') || $request->user()?->hasPermission('ledger.view'), 403);

        $ents = VendorEntitlement::query()
            ->with(['vendor', 'order'])
            ->latest('id')
            ->paginate(20);

        return view('admin.finance.entitlements', compact('ents'));
    }

    public function reports(Request $request, VendorPayableService $payables): View
    {
        abort_unless($request->user()?->hasPermission('finance.reports.view'), 403);

        $currency = config('finance.currency', 'TZS');
        $gross = VendorEntitlement::query()->sum('gross_amount');
        $commission = VendorEntitlement::query()->sum('commission_amount');
        $refundedNet = VendorEntitlement::query()->sum('refunded_net');
        $completedPayouts = VendorPayout::query()->where('status', 'completed')->sum('amount');
        $pendingPayouts = VendorPayout::query()->whereIn('status', ['pending', 'approved', 'processing'])->sum('amount');

        $report = [
            'currency' => $currency,
            'gross_sales' => number_format((float) $gross, 2, '.', ''),
            'platform_commission' => number_format((float) $commission, 2, '.', ''),
            'refunds_net' => number_format((float) $refundedNet, 2, '.', ''),
            'completed_payouts' => number_format((float) $completedPayouts, 2, '.', ''),
            'pending_payouts' => number_format((float) $pendingPayouts, 2, '.', ''),
        ];

        return view('admin.finance.reports', compact('report'));
    }
}
