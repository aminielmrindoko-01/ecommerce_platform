<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

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

    public function add($id)
    {
        $product = Product::findOrFail($id);
        $cart = $this->getCart();

        if (isset($cart[$id])) {
            $cart[$id]['quantity']++;
        } else {
            $cart[$id] = [
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => 1,
            ];
        }

        $this->setCart($cart);

        return redirect()->route('cart.index');
    }

    public function index()
    {
        $cart = $this->getCart();
        return view('cart', compact('cart'));
    }

    public function remove($id)
    {
        $cart = $this->getCart();

        if (isset($cart[$id])) {
            unset($cart[$id]);
            $this->setCart($cart);
        }

        return redirect()->route('cart.index');
    }

    public function increase($id)
    {
        $cart = $this->getCart();

        if (isset($cart[$id])) {
            $cart[$id]['quantity']++;
            $this->setCart($cart);
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
}
