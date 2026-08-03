<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\Operations\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ReturnController extends Controller
{
    public function index(): View
    {
        $vendor = auth()->user()->vendor;
        abort_unless($vendor, 403);

        $returns = ReturnRequest::query()
            ->with(['items.orderItem', 'order', 'customer'])
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->paginate(20);

        return view('vendor.returns.index', compact('returns'));
    }

    public function show(ReturnRequest $return): View
    {
        $vendor = auth()->user()->vendor;
        abort_unless($vendor && (int) $return->vendor_id === (int) $vendor->id, 403);
        $return->load(['items.orderItem', 'order', 'customer', 'paymentRefund']);

        return view('vendor.returns.show', compact('return'));
    }

    public function approve(ReturnRequest $return, ReturnService $returns): RedirectResponse
    {
        $this->assertOwn($return);

        try {
            $returns->approve($return, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('success', 'Return approved.');
    }

    public function reject(Request $request, ReturnRequest $return, ReturnService $returns): RedirectResponse
    {
        $this->assertOwn($return);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $returns->reject($return, auth()->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('success', 'Return rejected.');
    }

    public function receive(Request $request, ReturnRequest $return, ReturnService $returns): RedirectResponse
    {
        $this->assertOwn($return);
        try {
            $returns->markReceived($return, auth()->user(), $request->boolean('restockable'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }

        return back()->with('success', 'Return marked received.');
    }

    protected function assertOwn(ReturnRequest $return): void
    {
        $vendor = auth()->user()->vendor;
        abort_unless($vendor && (int) $return->vendor_id === (int) $vendor->id, 403);
    }
}
