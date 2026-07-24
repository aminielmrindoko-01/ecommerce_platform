<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Marketplace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function show()
    {
        $cart = session('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $addresses = auth()->user()?->addresses()->latest()->get() ?? collect();
        $couponCode = session('coupon_code');
        $coupon = $couponCode ? Coupon::where('code', $couponCode)->first() : null;

        $subtotal = collect($cart)->sum(fn ($item) => $item['price'] * $item['quantity']);
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
            'shippingRegion'
        ));
    }

    public function place(Request $request)
    {
        $cart = session('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $data = $request->validate([
            'full_name' => 'required|string|max:120',
            'phone' => 'required|string|max:40',
            'line1' => 'required|string|max:180',
            'line2' => 'nullable|string|max:180',
            'city' => 'required|string|max:80',
            'region' => 'nullable|string|max:80',
            'payment_method' => 'required|string|max:40',
            'shipping_method' => 'required|in:standard,express,pickup',
            'save_address' => 'nullable|boolean',
        ]);

        $couponCode = session('coupon_code');
        $coupon = $couponCode ? Coupon::where('code', $couponCode)->first() : null;
        $subtotal = collect($cart)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0;

        $shippingMap = [
            'standard' => $subtotal >= 150000 ? 0 : 8000,
            'express' => 18000,
            'pickup' => 0,
        ];
        $shipping = $shippingMap[$data['shipping_method']];
        $tax = round(($subtotal - $discount) * Marketplace::taxRate(), 2);
        $total = max(0, $subtotal - $discount + $shipping + $tax);

        $order = DB::transaction(function () use ($cart, $data, $shipping, $tax, $discount, $couponCode, $total) {
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
                'payment_method' => $data['payment_method'],
                'shipping_method' => $data['shipping_method'],
                'shipping_cost' => $shipping,
                'tax_amount' => $tax,
                'discount_amount' => $discount,
                'coupon_code' => $couponCode,
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

            foreach ($cart as $productId => $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);

                Product::where('id', $productId)->where('stock', '>=', $item['quantity'])->decrement('stock', $item['quantity']);
                Product::where('id', $productId)->increment('sold_count', $item['quantity']);
            }

            return $order;
        });

        session()->forget(['cart', 'coupon_code']);

        // Payment handlers are stubbed: real gateways (Stripe / M-Pesa) require configured keys.
        return redirect()->route('checkout.confirmation', $order)->with('success', 'Order placed! Complete payment using your selected method.');
    }

    public function confirmation(Order $order)
    {
        abort_unless($order->user_id === auth()->id() || auth()->user()?->isAdmin(), 403);

        $order->load('items.product');

        return view('checkout-confirmation', compact('order'));
    }

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
}
