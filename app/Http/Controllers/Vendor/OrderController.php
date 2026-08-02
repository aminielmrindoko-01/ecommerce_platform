<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\View\View;

/**
 * Vendor order visibility: only orders containing this vendor's products,
 * and only that vendor's line items + vendor subtotal.
 */
class OrderController extends Controller
{
    /**
     * Orders that include at least one product from this vendor.
     */
    public function index(): View
    {
        $vendorId = auth()->user()->vendor->id;

        $orderIds = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
            ->distinct()
            ->pluck('order_id');

        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->with(['items' => function ($q) use ($vendorId) {
                $q->whereHas('product', fn ($pq) => $pq->where('vendor_id', $vendorId))
                    ->with('product');
            }, 'user'])
            ->latest()
            ->paginate(15);

        $orders->getCollection()->transform(function (Order $order) {
            $order->setAttribute(
                'vendor_subtotal',
                $order->items->reduce(
                    fn ($carry, $item) => bcadd($carry, bcmul((string) $item->price, (string) $item->quantity, 2), 2),
                    '0.00'
                )
            );

            return $order;
        });

        return view('vendor.orders.index', compact('orders'));
    }

    /**
     * Single order detail scoped to this vendor's line items only.
     */
    public function show(Order $order): View
    {
        $vendorId = auth()->user()->vendor->id;

        $hasVendorItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
            ->exists();

        abort_unless($hasVendorItems, 403);

        $order->load(['user', 'items' => function ($q) use ($vendorId) {
            $q->whereHas('product', fn ($pq) => $pq->where('vendor_id', $vendorId))
                ->with('product');
        }]);

        $vendorSubtotal = $order->items->reduce(
            fn ($carry, $item) => bcadd($carry, bcmul((string) $item->price, (string) $item->quantity, 2), 2),
            '0.00'
        );

        return view('vendor.orders.show', compact('order', 'vendorSubtotal'));
    }
}
