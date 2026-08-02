<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vendor store profile (own store only).
 */
class ProfileController extends Controller
{
    /**
     * Show editable store profile for the authenticated vendor.
     */
    public function edit(): View
    {
        $vendor = auth()->user()->vendor;
        $this->authorize('update', $vendor);

        return view('vendor.profile', compact('vendor'));
    }

    /**
     * Update store profile fields. user_id / is_verified are not writable here.
     */
    public function update(Request $request): RedirectResponse
    {
        $vendor = auth()->user()->vendor;
        $this->authorize('update', $vendor);

        $data = $request->validate([
            'store_name' => 'required|string|max:120',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:120',
        ]);

        $vendor->fill($data);
        $vendor->save();

        return back()->with('success', 'Store profile updated.');
    }
}
