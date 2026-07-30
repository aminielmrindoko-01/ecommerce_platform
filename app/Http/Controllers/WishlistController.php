<?php

/**
 * |--------------------------------------------------------------------------
 * | Wishlist toggle
 * |--------------------------------------------------------------------------
 * | Auth-only. Unique (user_id, product_id) enforced at DB level.
 */

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Buyer wishlist listing and add/remove toggle.
 *
 * @package App\Http\Controllers
 */
class WishlistController extends Controller
{
    /**
     * Render the account wishlist view for the current user.
     */
    public function index(): View
    {
        $items = Wishlist::with('product.vendor')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('account.wishlist', compact('items'));
    }

    /**
     * Add product to wishlist or remove if already present (idempotent toggle).
     *
     * @param  int|string  $id  Product id
     */
    public function toggle($id): RedirectResponse
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
