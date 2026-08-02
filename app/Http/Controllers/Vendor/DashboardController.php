<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Vendor dashboard with store-scoped KPIs and fulfillment counters.
 */
class DashboardController extends Controller
{
    /**
     * Metrics and recent activity for the authenticated vendor's store.
     */
    public function index(): View
    {
        $vendor = auth()->user()->vendor;
        $vendorId = $vendor->id;

        $vendorItems = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId));

        $totalProducts = Product::where('vendor_id', $vendorId)->count();
        $activeProducts = Product::where('vendor_id', $vendorId)->where('stock', '>', 0)->count();
        $lowStock = Product::where('vendor_id', $vendorId)->where('stock', '<', 10)->count();

        $orderIds = (clone $vendorItems)->distinct()->pluck('order_id');
        $totalOrders = $orderIds->count();

        $fulfillmentCounts = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
            ->selectRaw('fulfillment_status, COUNT(*) as total')
            ->groupBy('fulfillment_status')
            ->pluck('total', 'fulfillment_status');

        $pendingFulfillment = (int) ($fulfillmentCounts['pending'] ?? 0);
        $confirmedFulfillment = (int) ($fulfillmentCounts['confirmed'] ?? 0);
        $processingFulfillment = (int) ($fulfillmentCounts['processing'] ?? 0);
        $shippedFulfillment = (int) ($fulfillmentCounts['shipped'] ?? 0);
        $deliveredFulfillment = (int) ($fulfillmentCounts['delivered'] ?? 0);
        $cancelledFulfillment = (int) ($fulfillmentCounts['cancelled'] ?? 0);

        $needsActionCount = $pendingFulfillment + $confirmedFulfillment + $processingFulfillment;

        $needsActionItems = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
            ->whereIn('fulfillment_status', ['pending', 'confirmed', 'processing'])
            ->with(['product', 'order'])
            ->latest()
            ->limit(8)
            ->get();

        $totalSales = (string) OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as vendor_sales')
            ->value('vendor_sales');

        $recentProducts = Product::where('vendor_id', $vendorId)->latest()->take(5)->get();

        $recentOrderIds = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
            ->latest()
            ->limit(20)
            ->pluck('order_id')
            ->unique()
            ->take(5);

        $recentOrders = Order::with(['items' => function ($q) use ($vendorId) {
            $q->whereHas('product', fn ($pq) => $pq->where('vendor_id', $vendorId))
                ->with('product');
        }])->whereIn('id', $recentOrderIds)->latest()->get();

        return view('vendor.dashboard', compact(
            'vendor',
            'totalProducts',
            'activeProducts',
            'lowStock',
            'totalOrders',
            'pendingFulfillment',
            'confirmedFulfillment',
            'processingFulfillment',
            'shippedFulfillment',
            'deliveredFulfillment',
            'cancelledFulfillment',
            'needsActionCount',
            'needsActionItems',
            'totalSales',
            'recentProducts',
            'recentOrders'
        ));
    }
}
