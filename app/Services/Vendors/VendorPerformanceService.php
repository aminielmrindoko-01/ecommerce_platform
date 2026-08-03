<?php

namespace App\Services\Vendors;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;

/**
 * Vendor performance metrics derived from real catalog/order data.
 * Metrics that require unimplemented features are marked unavailable.
 */
class VendorPerformanceService
{
    /**
     * @return array{
     *   total_products:int,
     *   published_products:int,
     *   total_order_items:int,
     *   completed_order_items:int,
     *   cancelled_order_items:int,
     *   sales_value:string,
     *   currency:string,
     *   average_rating:float|null,
     *   return_rate:null,
     *   fulfillment_performance:null,
     *   unavailable:list<string>
     * }
     */
    public function forVendor(Vendor $vendor): array
    {
        $vendorId = (int) $vendor->id;

        $totalProducts = Product::query()->where('vendor_id', $vendorId)->count();
        $published = Product::query()
            ->where('vendor_id', $vendorId)
            ->where('status', Product::STATUS_PUBLISHED)
            ->count();

        $itemStats = OrderItem::query()
            ->where('vendor_id', $vendorId)
            ->selectRaw("
                COUNT(*) as total_order_items,
                SUM(CASE WHEN fulfillment_status = 'delivered' THEN 1 ELSE 0 END) as completed_order_items,
                SUM(CASE WHEN fulfillment_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_order_items,
                COALESCE(SUM(price * quantity), 0) as sales_value
            ")
            ->first();

        return [
            'total_products' => $totalProducts,
            'published_products' => $published,
            'total_order_items' => (int) ($itemStats->total_order_items ?? 0),
            'completed_order_items' => (int) ($itemStats->completed_order_items ?? 0),
            'cancelled_order_items' => (int) ($itemStats->cancelled_order_items ?? 0),
            'sales_value' => number_format((float) ($itemStats->sales_value ?? 0), 2, '.', ''),
            'currency' => 'TZS',
            'average_rating' => $vendor->rating_avg !== null ? (float) $vendor->rating_avg : null,
            // Not yet supported by schema / returns flow:
            'return_rate' => null,
            'fulfillment_performance' => null,
            'unavailable' => [
                'return_rate',
                'fulfillment_performance',
            ],
        ];
    }
}
