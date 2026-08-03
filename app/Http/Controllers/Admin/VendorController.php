<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vendor;
use App\Services\Vendors\VendorLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class VendorController extends Controller
{
    public function __construct(
        protected VendorLifecycleService $lifecycle,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('vendors.view'), 403);

        $query = Vendor::query()->with(['user', 'reviewer'])->withCount([
            'products',
            'products as published_products_count' => fn ($q) => $q->where('status', 'published'),
        ]);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('store_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $vendors = $query->latest()->paginate(20)->withQueryString();

        // Real performance aggregates (orders via order_items.vendor_id when present).
        $performance = app(\App\Services\Vendors\VendorPerformanceService::class);
        $salesByVendor = \App\Models\OrderItem::query()
            ->selectRaw('vendor_id, COUNT(*) as item_rows, SUM(price * quantity) as sales_value')
            ->whereNotNull('vendor_id')
            ->groupBy('vendor_id')
            ->get()
            ->keyBy('vendor_id');

        $vendorMetrics = [];
        foreach ($vendors as $vendor) {
            $vendorMetrics[$vendor->id] = $performance->forVendor($vendor);
        }

        return view('admin.vendors.index', compact('vendors', 'salesByVendor', 'vendorMetrics'));
    }

    public function transition(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', VendorLifecycleService::STATUSES),
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->lifecycle->transition($vendor, $data['status'], $request->user(), $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Vendor status updated.');
    }
}
