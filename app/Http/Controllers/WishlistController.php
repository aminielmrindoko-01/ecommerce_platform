<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;

class WishlistController extends Controller
{
    public function index()
    {
        $items = Wishlist::with('product.vendor')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('account.wishlist', compact('items'));
    }

    public function toggle($id)
    {
        $product = Product::findOrFail($id);

        $existing = Wishlist::where('user_id', auth()->id())
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Removed from wishlist.');
        }

        Wishlist::create([
            'user_id' => auth()->id(),
            'product_id' => $product->id,
        ]);

        return back()->with('success', 'Saved to wishlist.');
    }
}
