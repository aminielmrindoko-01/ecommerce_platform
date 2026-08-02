<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateOrderItemFulfillmentRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderItemFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Vendor order visibility + fulfillment updates for owned line items only.
 */
class OrderController extends Controller
{
    public function __construct(
        protected OrderItemFulfillmentService $fulfillment
    ) {}

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
                    fn ($carry, $item) => bcadd($carry, $item->lineTotal(), 2),
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
            fn ($carry, $item) => bcadd($carry, $item->lineTotal(), 2),
            '0.00'
        );

        $shipping = $this->fulfillmentShipping($order);
        $allowedByItem = [];
        foreach ($order->items as $item) {
            $allowedByItem[$item->id] = $this->fulfillment->allowedTransitions($item);
        }

        return view('vendor.orders.show', compact(
            'order',
            'vendorSubtotal',
            'shipping',
            'allowedByItem'
        ));
    }

    /**
     * Update fulfillment status for one of this vendor's order items.
     */
    public function updateFulfillment(
        UpdateOrderItemFulfillmentRequest $request,
        Order $order,
        OrderItem $orderItem
    ): RedirectResponse {
        abort_unless((int) $orderItem->order_id === (int) $order->id, 404);

        $this->authorize('updateFulfillment', $orderItem);

        try {
            $this->fulfillment->transition(
                $orderItem,
                $request->validated('fulfillment_status')
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Fulfillment status updated.');
    }

    /**
     * Minimum shipping fields needed for vendor fulfillment (no account email).
     *
     * @return array<string, string|null>
     */
    protected function fulfillmentShipping(Order $order): array
    {
        $address = $order->shipping_address ?? [];

        return [
            'full_name' => $address['full_name'] ?? $order->user?->name,
            'phone' => $address['phone'] ?? null,
            'line1' => $address['line1'] ?? null,
            'line2' => $address['line2'] ?? null,
            'city' => $address['city'] ?? null,
            'region' => $address['region'] ?? null,
        ];
    }
}
