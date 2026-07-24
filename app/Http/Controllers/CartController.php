<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Product;
use App\Support\Marketplace;
use Illuminate\Http\Request;

class CartController extends Controller
{
    protected function getCart(): array
    {
        return session()->get('cart', []);
    }

    protected function setCart(array $cart): void
    {
        session()->put('cart', $cart);
    }

    protected function getSaved(): array
    {
        return session()->get('saved_for_later', []);
    }

    protected function setSaved(array $items): void
    {
        session()->put('saved_for_later', $items);
    }

    public function add(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        if ($product->stock < 1) {
            return back()->with('error', 'This product is out of stock.');
        }

        $qty = max(1, (int) $request->input('quantity', 1));
        $cart = $this->getCart();

        $cart[$id] = [
            'name' => $product->name,
            'price' => (float) $product->price,
            'quantity' => ($cart[$id]['quantity'] ?? 0) + $qty,
            'image' => $product->image_url,
            'brand' => $product->brand,
        ];

        if ($cart[$id]['quantity'] > $product->stock) {
            $cart[$id]['quantity'] = $product->stock;
        }

        $this->setCart($cart);

        return redirect()->route('cart.index')->with('success', 'Added to cart.');
    }

    public function index()
    {
        $cart = $this->getCart();
        $saved = $this->getSaved();
        $couponCode = session('coupon_code');
        $coupon = $couponCode ? Coupon::where('code', $couponCode)->first() : null;

        $subtotal = collect($cart)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0;
        $shipping = $subtotal >= 150000 || $subtotal === 0.0 ? 0 : 8000;
        $tax = round(($subtotal - $discount) * Marketplace::taxRate(), 2);
        $total = max(0, $subtotal - $discount + $shipping + $tax);

        return view('cart', compact('cart', 'saved', 'subtotal', 'discount', 'shipping', 'tax', 'total', 'couponCode'));
    }

    public function remove($id)
    {
        $cart = $this->getCart();
        unset($cart[$id]);
        $this->setCart($cart);

        return redirect()->route('cart.index')->with('success', 'Item removed.');
    }

    public function increase($id)
    {
        $cart = $this->getCart();
        $product = Product::find($id);

        if (isset($cart[$id])) {
            $max = $product?->stock ?? 99;
            if ($cart[$id]['quantity'] < $max) {
                $cart[$id]['quantity']++;
                $this->setCart($cart);
            }
        }

        return redirect()->route('cart.index');
    }

    public function decrease($id)
    {
        $cart = $this->getCart();

        if (isset($cart[$id])) {
            $cart[$id]['quantity']--;
            if ($cart[$id]['quantity'] <= 0) {
                unset($cart[$id]);
            }
            $this->setCart($cart);
        }

        return redirect()->route('cart.index');
    }

    public function applyCoupon(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40',
        ]);

        $cart = $this->getCart();
        $subtotal = collect($cart)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $coupon = Coupon::where('code', strtoupper(trim($data['code'])))->first();

        if (! $coupon || ! $coupon->isValid($subtotal)) {
            return back()->with('error', 'Invalid or expired coupon for this order.');
        }

        session(['coupon_code' => $coupon->code]);

        return back()->with('success', 'Coupon applied: '.$coupon->code);
    }

    public function removeCoupon()
    {
        session()->forget('coupon_code');

        return back()->with('success', 'Coupon removed.');
    }

    public function saveForLater($id)
    {
        $cart = $this->getCart();
        $saved = $this->getSaved();

        if (isset($cart[$id])) {
            $saved[$id] = $cart[$id];
            unset($cart[$id]);
            $this->setCart($cart);
            $this->setSaved($saved);
        }

        return back()->with('success', 'Saved for later.');
    }

    public function moveToCart($id)
    {
        $cart = $this->getCart();
        $saved = $this->getSaved();

        if (isset($saved[$id])) {
            $cart[$id] = $saved[$id];
            unset($saved[$id]);
            $this->setCart($cart);
            $this->setSaved($saved);
        }

        return back()->with('success', 'Moved to cart.');
    }
}
