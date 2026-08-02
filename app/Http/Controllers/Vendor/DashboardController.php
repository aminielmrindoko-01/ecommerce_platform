<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Vendor dashboard with store-scoped KPIs only.
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

        $totalProducts = Product::where('vendor_id', $vendorId)->count();
        $activeProducts = Product::where('vendor_id', $vendorId)->where('stock', '>', 0)->count();
        $lowStock = Product::where('vendor_id', $vendorId)->where('stock', '<', 10)->count();

        $orderIds = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
            ->distinct()
            ->pluck('order_id');

        $totalOrders = $orderIds->count();
        $pendingOrders = Order::whereIn('id', $orderIds)->where('status', 'pending')->count();
        $completedOrders = Order::whereIn('id', $orderIds)->where('status', 'completed')->count();

        // Sales = sum of this vendor's line totals only (not orders.total_price).
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
            'pendingOrders',
            'completedOrders',
            'totalSales',
            'recentProducts',
            'recentOrders'
        ));
    }
}
