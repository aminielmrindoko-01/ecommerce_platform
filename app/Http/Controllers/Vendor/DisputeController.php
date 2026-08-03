<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Services\Operations\DisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class DisputeController extends Controller
{
    public function index(): View
    {
        $vendor = auth()->user()->vendor;
        abort_unless($vendor, 403);

        $disputes = Dispute::query()
            ->with(['order', 'customer', 'orderItem'])
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->paginate(20);

        return view('vendor.disputes.index', compact('disputes'));
    }

    public function show(Dispute $dispute, DisputeService $disputes): View
    {
        $vendor = auth()->user()->vendor;
        abort_unless($vendor && (int) $dispute->vendor_id === (int) $vendor->id, 403);

        try {
            $disputes->assertCanAccess($dispute, auth()->user());
        } catch (InvalidArgumentException) {
            abort(403);
        }

        $dispute->load(['messages.author', 'order', 'customer', 'orderItem', 'settlementHold']);

        return view('vendor.disputes.show', compact('dispute'));
    }

    public function respond(Request $request, Dispute $dispute, DisputeService $disputes): RedirectResponse
    {
        $vendor = auth()->user()->vendor;
        abort_unless($vendor && (int) $dispute->vendor_id === (int) $vendor->id, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'evidence_ref' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $disputes->respond($dispute, auth()->user(), $data['body'], $data['evidence_ref'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['dispute' => $e->getMessage()]);
        }

        return back()->with('success', 'Response sent.');
    }
}
