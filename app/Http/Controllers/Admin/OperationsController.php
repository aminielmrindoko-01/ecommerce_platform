<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chargeback;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\SettlementHold;
use App\Models\Vendor;
use App\Services\Operations\ChargebackService;
use App\Services\Operations\CommissionConfigService;
use App\Services\Operations\DisputeService;
use App\Services\Operations\ReturnService;
use App\Services\Operations\SettlementHoldService;
use App\Services\Operations\VendorFinancialStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class OperationsController extends Controller
{
    public function returns(): View
    {
        $returns = ReturnRequest::query()
            ->with(['order', 'vendor', 'customer', 'items'])
            ->latest()
            ->paginate(30);

        return view('admin.operations.returns', compact('returns'));
    }

    public function showReturn(ReturnRequest $return): View
    {
        $return->load(['order', 'vendor', 'customer', 'items.orderItem', 'paymentRefund', 'settlementHold']);

        return view('admin.operations.return-show', compact('return'));
    }

    public function approveReturn(ReturnRequest $return, ReturnService $returns): RedirectResponse
    {
        try {
            $returns->approve($return, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()]);
        }

        return back()->with('success', 'Return approved.');
    }

    public function rejectReturn(Request $request, ReturnRequest $return, ReturnService $returns): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            $returns->reject($return, auth()->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()]);
        }

        return back()->with('success', 'Return rejected.');
    }

    public function receiveReturn(Request $request, ReturnRequest $return, ReturnService $returns): RedirectResponse
    {
        try {
            $returns->markReceived($return, auth()->user(), $request->boolean('restockable'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()]);
        }

        return back()->with('success', 'Return received.');
    }

    public function refundReturn(ReturnRequest $return, ReturnService $returns): RedirectResponse
    {
        try {
            $returns->processRefund($return, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()]);
        }

        return back()->with('success', 'Return refund processed.');
    }

    public function disputes(): View
    {
        $disputes = Dispute::query()
            ->with(['order', 'vendor', 'customer'])
            ->latest()
            ->paginate(30);

        return view('admin.operations.disputes', compact('disputes'));
    }

    public function showDispute(Dispute $dispute): View
    {
        $dispute->load(['messages.author', 'order', 'vendor', 'customer', 'settlementHold']);

        return view('admin.operations.dispute-show', compact('dispute'));
    }

    public function resolveDispute(Request $request, Dispute $dispute, DisputeService $disputes): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:resolved_customer,resolved_vendor,partially_resolved,closed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $disputes->resolve($dispute, auth()->user(), $data['status'], $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()]);
        }

        return back()->with('success', 'Dispute updated.');
    }

    public function respondDispute(Request $request, Dispute $dispute, DisputeService $disputes): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $disputes->respond($dispute, auth()->user(), $data['body']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()]);
        }

        return back()->with('success', 'Response added.');
    }

    public function chargebacks(): View
    {
        $chargebacks = Chargeback::query()
            ->with(['order', 'vendor'])
            ->latest()
            ->paginate(30);

        return view('admin.operations.chargebacks', compact('chargebacks'));
    }

    public function storeChargeback(Request $request, ChargebackService $chargebacks): RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'provider_reference' => ['nullable', 'string', 'max:128'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
        ]);

        $order = Order::query()->findOrFail($data['order_id']);

        try {
            $chargebacks->receive(
                $order,
                (string) $data['amount'],
                auth()->user(),
                $data['provider_reference'] ?? null,
                $data['reason'] ?? null,
                isset($data['vendor_id']) ? (int) $data['vendor_id'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Chargeback recorded (internal).');
    }

    public function updateChargeback(Request $request, Chargeback $chargeback, ChargebackService $chargebacks): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:under_review,responded,accepted,lost,won,closed'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $chargebacks->updateStatus($chargeback, $data['status'], auth()->user(), $data['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()]);
        }

        return back()->with('success', 'Chargeback updated.');
    }

    public function holds(): View
    {
        $holds = SettlementHold::query()
            ->with(['vendor', 'order'])
            ->latest()
            ->paginate(40);

        return view('admin.operations.holds', compact('holds'));
    }

    public function releaseHold(SettlementHold $hold, SettlementHoldService $holds): RedirectResponse
    {
        try {
            $holds->release($hold, auth()->user(), 'Manual release');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()]);
        }

        return back()->with('success', 'Hold released.');
    }

    public function commission(): View
    {
        $platform = app(CommissionConfigService::class)->resolveForVendor(null);
        $overrides = \App\Models\CommissionConfig::query()
            ->where('scope', 'vendor')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return view('admin.operations.commission', compact('platform', 'overrides'));
    }

    public function updateCommission(Request $request, CommissionConfigService $configs): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:percentage,fixed'],
            'rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $configs->updatePlatform(
                auth()->user(),
                $data['type'],
                (string) $data['rate'],
                (string) ($data['fixed_amount'] ?? '0'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()]);
        }

        return back()->with('success', 'Platform commission updated. Historical entitlements are unchanged.');
    }

    public function setVendorFinancialStatus(
        Request $request,
        Vendor $vendor,
        VendorFinancialStatusService $statuses,
    ): RedirectResponse {
        $data = $request->validate([
            'financial_status' => ['required', 'in:active,payout_hold,financial_review,suspended'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $statuses->setStatus($vendor, $data['financial_status'], auth()->user(), $data['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['ops' => $e->getMessage()]);
        }

        return back()->with('success', 'Vendor financial status updated.');
    }
}
