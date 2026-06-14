<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Session;

class CartController extends Controller
{
    // Add to cart
    public function add($id)
    {
        $product = Product::findOrFail($id);

        $cart = Session::get('cart', []);

        if (isset($cart[$id])) {
            $cart[$id]['quantity']++;
        } else {
            $cart[$id] = [
                "name" => $product->name,
                "price" => $product->price,
                "quantity" => 1
            ];
        }

        Session::put('cart', $cart);

        return redirect('/cart');
    }

    // Show cart
    public function index()
    {
        $cart = Session::get('cart', []);
        return view('cart', compact('cart'));
    }
    public function remove($id)
{
    $cart = session()->get('cart', []);

    if (isset($cart[$id])) {
        unset($cart[$id]);
        session()->put('cart', $cart);
    }

    return redirect('/cart');
}
public function increase($id)
{
    $cart = session()->get('cart', []);

    if (isset($cart[$id])) {
        $cart[$id]['quantity']++;
        session()->put('cart', $cart);
    }

    return redirect('/cart');
}

public function decrease($id)
{
    $cart = session()->get('cart', []);

    if (isset($cart[$id])) {
        $cart[$id]['quantity']--;

        if ($cart[$id]['quantity'] <= 0) {
            unset($cart[$id]);
        }

        session()->put('cart', $cart);
    }

    return redirect('/cart');
}

}