<?php

/**
 * |--------------------------------------------------------------------------
 * | Checkout & order placement
 * |--------------------------------------------------------------------------
 * | Auth-only. Recalculates totals from live product rows (never trust session
 * | prices). Payment gateways are stubbed — orders stay `pending` after place().
 * | Stock is locked and decremented atomically; insufficient stock aborts.
 */

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CheckoutIdempotencyService;
use App\Services\PaymentService;
use App\Support\Marketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Checkout form, transactional order creation, and confirmation page.
 *
 * @package App\Http\Controllers
 */
class CheckoutController extends Controller
{
    /**
     * Allowed payment method keys shown on the checkout form (integrations stubbed).
     *
     * @return array<string, string>
     */
    protected function paymentMethods(): array
    {
        return [
            'card' => 'Visa / Mastercard / Amex',
            'paypal' => 'PayPal',
            'apple_pay' => 'Apple Pay',
            'google_pay' => 'Google Pay',
            'stripe' => 'Stripe',
            'mpesa' => 'M-Pesa',
            'airtel' => 'Airtel Money',
            'tigo' => 'Tigo Pesa',
            'halopesa' => 'HaloPesa',
            'mixx' => 'Mixx by Yas',
            'mtn' => 'MTN MoMo',
            'orange' => 'Orange Money',
            'bank' => 'Bank transfer',
            'cod' => 'Cash on delivery',
        ];
    }

    /**
     * Rebuild cart lines from the database so session prices cannot be trusted.
     *
     * @param  array<int|string, array<string, mixed>>  $sessionCart
     * @return array{lines: array<int, array{product: Product, quantity: int, unit_price: float, line_total: float}>, errors: list<string>}
     */
    protected function resolveCartFromDatabase(array $sessionCart): array
    {
        $lines = [];
        $errors = [];

        foreach ($sessionCart as $productId => $item) {
            $product = Product::query()->find($productId);

            if (! $product) {
                $errors[] = 'A product in your cart is no longer available.';
                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($product->stock < 1) {
                $errors[] = "{$product->name} is out of stock.";
                continue;
            }

            if ($quantity > $product->stock) {
                $errors[] = "{$product->name} only has {$product->stock} in stock.";
                $quantity = $product->stock;
            }

            $unitPrice = (float) $product->price;
            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $quantity,
            ];
        }

        return compact('lines', 'errors');
    }

    /**
     * Show checkout with address book, shipping options, tax, and payment methods.
     */
    public function show(): View|RedirectResponse
    {
        $sessionCart = session('cart', []);

        if (empty($sessionCart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $resolved = $this->resolveCartFromDatabase($sessionCart);

        if (empty($resolved['lines'])) {
            session()->forget('cart');

            return redirect()->route('cart.index')->with('error', 'Your cart is empty or no longer available.');
        }

        if (! empty($resolved['errors'])) {
            return redirect()->route('cart.index')->with('error', implode(' ', $resolved['errors']));
        }

        $cart = [];
        foreach ($resolved['lines'] as $line) {
            $product = $line['product'];
            $cart[$product->id] = [
                'name' => $product->name,
                'price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'image' => $product->image_url,
                'brand' => $product->brand,
            ];
        }
        session(['cart' => $cart]);

        $addresses = auth()->user()?->addresses()->latest()->get() ?? collect();
        $couponCode = session('coupon_code');
        $coupon = $couponCode ? Coupon::where('code', $couponCode)->first() : null;

        $subtotal = collect($resolved['lines'])->sum('line_total');
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0;
        $shippingOptions = [
            'standard' => ['label' => 'Standard (3–5 days)', 'cost' => $subtotal >= 150000 ? 0 : 8000],
            'express' => ['label' => 'Express (1–2 days)', 'cost' => 18000],
            'pickup' => ['label' => 'Store pickup', 'cost' => 0],
        ];
        $shipping = $shippingOptions['standard']['cost'];
        $taxRate = Marketplace::taxRate();
        $tax = round(($subtotal - $discount) * $taxRate, 2);
        $total = max(0, $subtotal - $discount + $shipping + $tax);

        $paymentMethods = $this->paymentMethods();
        $phonePrefix = Marketplace::countries()[Marketplace::country()]['phone'] ?? '+255';
        $shippingRegion = Marketplace::shippingRegions()[Marketplace::countries()[Marketplace::country()]['shipping']] ?? 'East Africa';

        $checkoutToken = app(CheckoutIdempotencyService::class)->issue(auth()->id());

        return view('checkout', compact(
            'cart',
            'addresses',
            'subtotal',
            'discount',
            'shipping',
            'shippingOptions',
            'tax',
            'taxRate',
            'total',
            'couponCode',
            'paymentMethods',
            'phonePrefix',
            'shippingRegion',
            'checkoutToken'
        ));
    }

    /**
     * Create order + items in a DB transaction, optionally save address, clear cart.
     *
     * Side effects: locks product rows, decrements stock, increments sold_count.
     * Checkout token consumption is atomic with order creation (lockForUpdate).
     * Rate-limited at the route layer (throttle:10,1).
     */
    public function place(
        Request $request,
        PaymentService $payments,
        CheckoutIdempotencyService $checkoutIds
    ): RedirectResponse {
        $sessionCart = session('cart', []);

        if (empty($sessionCart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $paymentKeys = array_keys($this->paymentMethods());

        $data = $request->validate([
            'full_name' => 'required|string|max:120',
            'phone' => 'required|string|max:40',
            'line1' => 'required|string|max:180',
            'line2' => 'nullable|string|max:180',
            'city' => 'required|string|max:80',
            'region' => 'nullable|string|max:80',
            'payment_method' => ['required', 'string', Rule::in($paymentKeys)],
            'shipping_method' => 'required|in:standard,express,pickup',
            'save_address' => 'nullable|boolean',
            'checkout_token' => 'required|string|max:64',
        ]);

        try {
            $order = DB::transaction(function () use ($sessionCart, $data, $checkoutIds) {
                $idempotencyKey = $checkoutIds->lockConsumable(
                    $data['checkout_token'],
                    (int) auth()->id()
                );

                $lines = [];
                $subtotal = 0.0;

                foreach ($sessionCart as $productId => $item) {
                    /** @var Product|null $product */
                    $product = Product::query()->whereKey($productId)->lockForUpdate()->first();

                    if (! $product) {
                        throw new \RuntimeException('A product in your cart is no longer available.');
                    }

                    $quantity = max(1, (int) ($item['quantity'] ?? 1));

                    if ($product->stock < $quantity) {
                        throw new \RuntimeException(
                            $product->stock < 1
                                ? "{$product->name} is out of stock."
                                : "{$product->name} only has {$product->stock} in stock."
                        );
                    }

                    $unitPrice = (float) $product->price;
                    $lines[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                    ];
                    $subtotal += $unitPrice * $quantity;
                }

                if ($lines === []) {
                    throw new \RuntimeException('Your cart is empty or no longer available.');
                }

                $couponCode = session('coupon_code');
                $coupon = $couponCode ? Coupon::where('code', $couponCode)->first() : null;
                $discount = $coupon ? $coupon->discountFor($subtotal) : 0;

                $shippingMap = [
                    'standard' => $subtotal >= 150000 ? 0 : 8000,
                    'express' => 18000,
                    'pickup' => 0,
                ];
                $shipping = $shippingMap[$data['shipping_method']];
                $tax = round(($subtotal - $discount) * Marketplace::taxRate(), 2);
                $total = max(0, $subtotal - $discount + $shipping + $tax);

                if ($data['save_address'] ?? false) {
                    Address::create([
                        'user_id' => auth()->id(),
                        'label' => 'Checkout',
                        'full_name' => $data['full_name'],
                        'phone' => $data['phone'],
                        'line1' => $data['line1'],
                        'line2' => $data['line2'] ?? null,
                        'city' => $data['city'],
                        'region' => $data['region'] ?? null,
                        'country' => 'Tanzania',
                        'is_default' => ! auth()->user()->addresses()->exists(),
                    ]);
                }

                $order = Order::create([
                    'order_number' => 'SN-'.strtoupper(Str::random(8)),
                    'user_id' => auth()->id(),
                    'total_price' => $total,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'payment_method' => $data['payment_method'],
                    'shipping_method' => $data['shipping_method'],
                    'shipping_cost' => $shipping,
                    'tax_amount' => $tax,
                    'discount_amount' => $discount,
                    'coupon_code' => $coupon?->code,
                    'shipping_address' => [
                        'full_name' => $data['full_name'],
                        'phone' => $data['phone'],
                        'line1' => $data['line1'],
                        'line2' => $data['line2'] ?? null,
                        'city' => $data['city'],
                        'region' => $data['region'] ?? null,
                        'country' => 'Tanzania',
                    ],
                ]);

                foreach ($lines as $line) {
                    /** @var Product $product */
                    $product = $line['product'];

                    OrderItem::recordPurchase(
                        $order->id,
                        $product->id,
                        $line['quantity'],
                        $line['unit_price']
                    );

                    $product->decrement('stock', $line['quantity']);
                    $product->increment('sold_count', $line['quantity']);
                }

                $checkoutIds->markConsumed($idempotencyKey, (int) $order->id);

                return $order;
            });
        } catch (InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'already processed')) {
                return redirect()
                    ->route('account.orders')
                    ->with('success', 'Your order was already placed. No duplicate order was created.');
            }

            return redirect()->route('checkout')->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        session()->forget(['cart', 'coupon_code']);

        $payments->ensurePendingTransaction($order, 'stub');

        OrderPlaced::dispatch($order->load('items.product.vendor.user'));

        // Payment handlers are stubbed: real gateways (Stripe / M-Pesa) require configured keys.
        return redirect()->route('checkout.confirmation', $order)->with('success', 'Order placed! Complete payment using your selected method.');
    }

    /**
     * Order confirmation — owner or admin only (IDOR protection).
     */
    public function confirmation(Order $order): View
    {
        abort_unless($order->user_id === auth()->id() || auth()->user()?->isAdmin(), 403);

        $order->load(['items.product', 'latestPaymentTransaction']);

        return view('checkout-confirmation', compact('order'));
    }
}
