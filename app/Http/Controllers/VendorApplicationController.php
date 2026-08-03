<?php

namespace App\Http\Controllers;

use App\Services\Vendors\VendorLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Customer → vendor onboarding application.
 */
class VendorApplicationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $user = auth()->user();
        if ($user?->vendor) {
            return redirect()->route('account.index')
                ->with('error', 'You already have a vendor application or store.');
        }

        return view('vendor.apply');
    }

    public function store(Request $request, VendorLifecycleService $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'store_name' => 'required|string|max:120',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:120',
            'application_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $lifecycle->apply($request->user(), $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['store_name' => $e->getMessage()]);
        }

        return redirect()->route('account.index')
            ->with('success', 'Vendor application submitted. An administrator will review it.');
    }
}
