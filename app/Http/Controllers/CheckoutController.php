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
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Services\CheckoutIdempotencyService;
use App\Services\Orders\OrderService;
use App\Services\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Support\Marketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

            if (! $product->isPublished()) {
                $errors[] = "{$product->name} is not available for purchase.";
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
    public function show(PaymentGatewayManager $gateways): View|RedirectResponse
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

        $paymentMethods = $gateways->checkoutMethods();
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
     * Create order via OrderService (server prices, inventory reserve+commit, audits).
     * Rate-limited at the route layer (throttle:10,1).
     */
    public function place(
        Request $request,
        PaymentService $payments,
        PaymentGatewayManager $gateways,
        OrderService $orders,
    ): RedirectResponse {
        $sessionCart = session('cart', []);

        if (empty($sessionCart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $paymentKeys = $gateways->methodKeys();

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

        foreach (['user_id', 'vendor_id', 'customer_id', 'role', 'permissions'] as $forbidden) {
            if ($request->exists($forbidden)) {
                abort(422, 'Invalid checkout payload.');
            }
        }

        // Financial / status fields from the client are ignored (never trusted).
        // OrderService recalculates prices and totals from the database.

        try {
            if ($data['save_address'] ?? false) {
                $address = new Address([
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
                $address->user_id = auth()->id();
                $address->save();
            }

            $order = $orders->place(auth()->user(), $sessionCart, [
                'payment_method' => $data['payment_method'],
                'shipping_method' => $data['shipping_method'],
                'checkout_token' => $data['checkout_token'],
                'coupon_code' => session('coupon_code'),
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

        $transaction = $payments->ensurePendingTransaction(
            $order,
            $this->paymentProviderForOrder($order)
        );
        $paymentInit = $gateways->initialize($order, $transaction);
        $payments->markInitiated($transaction->fresh());

        OrderPlaced::dispatch($order->load('items.product.vendor.user'));

        return redirect()
            ->route('checkout.confirmation', $order)
            ->with('success', 'Order placed successfully.')
            ->with('payment_init', $paymentInit->toArray());
    }

    /**
     * Order confirmation — owner or admin only (IDOR protection).
     */
    public function confirmation(Order $order, PaymentGatewayManager $gateways, PaymentService $payments): View
    {
        abort_unless($order->user_id === auth()->id() || auth()->user()?->isAdmin(), 403);

        $order->load(['items.product', 'latestPaymentTransaction']);

        $transaction = $order->latestPaymentTransaction
            ?? $payments->ensurePendingTransaction(
                $order,
                $this->paymentProviderForOrder($order)
            );

        $paymentInit = session('payment_init')
            ?? $gateways->initialize($order, $transaction)->toArray();

        return view('checkout-confirmation', compact('order', 'paymentInit'));
    }

    /**
     * Map configured method gateway to a PaymentTransaction provider key.
     */
    protected function paymentProviderForOrder(Order $order): string
    {
        $gateway = (string) config(
            'payments.methods.'.($order->payment_method ?: '').'.gateway',
            'stub'
        );

        return in_array($gateway, PaymentTransaction::PROVIDERS, true) ? $gateway : 'stub';
    }
}
