<?php

namespace App\Services\Orders;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Authorization\AuditLogger;
use App\Services\Catalog\InventoryService;
use App\Services\CheckoutIdempotencyService;
use App\Support\Marketplace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Transactional multi-vendor order creation + cancellation.
 * Prices/totals are server-calculated (never trusted from the client).
 */
class OrderService
{
    public const CURRENCY = 'TZS';

    public function __construct(
        protected InventoryService $inventory,
        protected OrderStateMachine $states,
        protected AuditLogger $audit,
        protected CheckoutIdempotencyService $checkoutIds,
    ) {}

    /**
     * Place an order from a trusted cart map (product_id => quantity).
     *
     * @param  array<int|string, array{quantity?:int}|int>  $cart
     * @param  array{
     *   payment_method:string,
     *   shipping_method:string,
     *   shipping_address:array<string,mixed>,
     *   checkout_token:string,
     *   coupon_code?:?string,
     * }  $checkout
     */
    public function place(User $customer, array $cart, array $checkout): Order
    {
        return DB::transaction(function () use ($customer, $cart, $checkout) {
            $idempotencyKey = $this->checkoutIds->lockConsumable(
                $checkout['checkout_token'],
                (int) $customer->id
            );

            $lines = $this->resolveValidatedLines($cart);
            $money = $this->calculateTotals(
                $lines,
                $checkout['shipping_method'],
                $checkout['coupon_code'] ?? session('coupon_code')
            );

            $order = new Order([
                'order_number' => 'SN-'.strtoupper(Str::random(8)),
                'total_price' => $money['total'],
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $checkout['payment_method'],
                'shipping_method' => $checkout['shipping_method'],
                'shipping_cost' => $money['shipping'],
                'tax_amount' => $money['tax'],
                'discount_amount' => $money['discount'],
                'coupon_code' => $money['coupon_code'],
                'shipping_address' => $checkout['shipping_address'],
            ]);
            $order->user_id = $customer->id;
            if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'currency')) {
                $order->currency = self::CURRENCY;
            }
            $order->save();

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $vendor = $product->vendor;

                OrderItem::recordPurchase(
                    orderId: $order->id,
                    productId: $product->id,
                    quantity: $line['quantity'],
                    unitPrice: $line['unit_price'],
                    vendorId: $vendor?->id,
                    productName: $product->name,
                    productSku: $product->sku,
                    vendorStoreName: $vendor?->store_name,
                );

                // Transitional reservation: reserve then immediately commit sale in the
                // same transaction (customer-visible parity with prior decrement path,
                // plus inventory movement audit). Split to payment-paid in Finance phase.
                $this->inventory->reserve(
                    $product,
                    $line['quantity'],
                    $customer,
                    (string) $order->id,
                );
                $this->inventory->commitSaleFromReserved(
                    $product->fresh(),
                    $line['quantity'],
                    $customer,
                    (string) $order->id,
                );
                $product->increment('sold_count', $line['quantity']);
            }

            $this->checkoutIds->markConsumed($idempotencyKey, (int) $order->id);

            $this->audit->log(
                action: 'ORDER_CREATED',
                actor: $customer,
                resourceType: 'order',
                resourceId: $order->id,
                newValues: [
                    'order_number' => $order->order_number,
                    'total_price' => (string) $order->total_price,
                    'currency' => self::CURRENCY,
                    'item_count' => count($lines),
                ],
            );

            return $order->fresh(['items']);
        });
    }

    public function cancel(Order $order, User $actor, ?string $reason = null): Order
    {
        if (! $this->actorMayCancel($order, $actor)) {
            throw new InvalidArgumentException('You are not allowed to cancel this order.');
        }

        $cancellable = ['pending', 'confirmed', 'paid'];
        if (! in_array($order->status, $cancellable, true)) {
            throw new InvalidArgumentException('This order can no longer be cancelled.');
        }

        return DB::transaction(function () use ($order, $actor, $reason) {
            $cancelled = $this->states->transition(
                $order,
                'cancelled',
                $actor,
                $reason ?? 'Order cancelled'
            );

            // Restock committed sale inventory (Phase 4 transitional reserve+commit).
            $cancelled->loadMissing('items.product');
            foreach ($cancelled->items as $item) {
                if (! $item->product) {
                    continue;
                }
                $this->inventory->adjust(
                    $item->product,
                    (int) $item->quantity,
                    'Order cancelled — stock restored',
                    $actor,
                    \App\Models\InventoryMovement::TYPE_RETURN,
                    'order',
                    (string) $cancelled->id,
                );
                $item->product->decrement('sold_count', min((int) $item->product->sold_count, (int) $item->quantity));
            }

            return $cancelled->fresh(['items']);
        });
    }

    public function actorMayCancel(Order $order, User $actor): bool
    {
        if (! $actor->isActiveAccount()) {
            return false;
        }

        if ($actor->hasPermission('orders.cancel') && $actor->hasPermission('orders.manage_any')) {
            return true;
        }

        // Customer: own pending/confirmed/paid only.
        if ((int) $order->user_id === (int) $actor->id && $actor->hasPermission('orders.view')) {
            return in_array($order->status, ['pending', 'confirmed', 'paid'], true);
        }

        return false;
    }

    /**
     * @param  array<int|string, array{quantity?:int}|int>  $cart
     * @return list<array{product:Product,quantity:int,unit_price:string}>
     */
    protected function resolveValidatedLines(array $cart): array
    {
        $lines = [];

        foreach ($cart as $productId => $item) {
            $quantity = is_array($item)
                ? max(1, (int) ($item['quantity'] ?? 1))
                : max(1, (int) $item);

            /** @var Product|null $product */
            $product = Product::query()
                ->with('vendor')
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();

            if (! $product || $product->trashed()) {
                throw new RuntimeException('A product in your cart is no longer available.');
            }

            if (! $product->isPublished()) {
                throw new RuntimeException("{$product->name} is not available for purchase.");
            }

            $vendor = $product->vendor;
            if ($vendor && ! $vendor->canSell()) {
                throw new RuntimeException("{$product->name} is not available from this seller.");
            }

            if ((int) $product->stock < $quantity) {
                throw new RuntimeException(
                    (int) $product->stock < 1
                        ? "{$product->name} is out of stock."
                        : "{$product->name} only has {$product->stock} in stock."
                );
            }

            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => number_format((float) $product->price, 2, '.', ''),
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('Your cart is empty or no longer available.');
        }

        return $lines;
    }

    /**
     * @param  list<array{product:Product,quantity:int,unit_price:string}>  $lines
     * @return array{subtotal:string,discount:string,shipping:string,tax:string,total:string,coupon_code:?string}
     */
    protected function calculateTotals(array $lines, string $shippingMethod, ?string $couponCode): array
    {
        $subtotal = '0.00';
        foreach ($lines as $line) {
            $lineTotal = bcmul($line['unit_price'], (string) $line['quantity'], 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);
        }

        $coupon = $couponCode ? Coupon::where('code', $couponCode)->first() : null;
        $discount = '0.00';
        if ($coupon) {
            $discount = number_format((float) $coupon->discountFor((float) $subtotal), 2, '.', '');
        }

        $shippingMap = [
            'standard' => bccomp($subtotal, '150000.00', 2) >= 0 ? '0.00' : '8000.00',
            'express' => '18000.00',
            'pickup' => '0.00',
        ];
        if (! isset($shippingMap[$shippingMethod])) {
            throw new InvalidArgumentException('Invalid shipping method.');
        }
        $shipping = $shippingMap[$shippingMethod];

        $taxable = bcsub($subtotal, $discount, 2);
        if (bccomp($taxable, '0.00', 2) < 0) {
            $taxable = '0.00';
        }
        $taxRate = number_format((float) Marketplace::taxRate(), 4, '.', '');
        $tax = bcmul($taxable, $taxRate, 2);
        $total = bcadd(bcadd($taxable, $shipping, 2), $tax, 2);
        if (bccomp($total, '0.00', 2) < 0) {
            $total = '0.00';
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'tax' => $tax,
            'total' => $total,
            'coupon_code' => $coupon?->code,
        ];
    }
}
