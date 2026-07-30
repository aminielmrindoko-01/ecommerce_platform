<?php

/**
 * |--------------------------------------------------------------------------
 * | Static / marketing shop pages
 * |--------------------------------------------------------------------------
 * | About, contact (throttled submit), vendors, categories, deals, blog,
 * | and newsletter subscribe (throttled; no persistence yet).
 */

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Non-catalog informational and listing pages for the storefront.
 *
 * @package App\Http\Controllers
 */
class ShopPageController extends Controller
{
    /** About / brand story page. */
    public function about(): View
    {
        return view('about');
    }

    /** Contact form page. */
    public function contact(): View
    {
        return view('contact');
    }

    /**
     * Validate contact message and flash success (no mail/queue integration yet).
     *
     * Throttled at route: throttle:5,1.
     */
    public function contactSubmit(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'message' => 'required|string|max:2000',
        ]);

        return back()->with('success', 'Message received — our support team will reply shortly.');
    }

    /** Public vendor directory (verified sellers first). */
    public function vendors(): View
    {
        $vendors = Vendor::withCount('products')->orderByDesc('is_verified')->orderBy('store_name')->get();

        return view('vendors', compact('vendors'));
    }

    /** Category grid with product counts. */
    public function categories(): View
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->get();

        return view('categories', compact('categories'));
    }

    /**
     * Flash-sale product listing with countdown timestamp for the deals UI.
     */
    public function deals(): View
    {
        $deals = Product::with(['vendor', 'category'])->flashSale()->latest()->paginate(12);
        $flashEndsAt = optional($deals->first()?->flash_ends_at)->getTimestamp() * 1000
            ?: now()->addHours(12)->getTimestamp() * 1000;

        return view('deals', compact('deals', 'flashEndsAt'));
    }

    /** Editorial / blog placeholder page. */
    public function blog(): View
    {
        return view('blog');
    }

    /**
     * Newsletter email capture — validates only; no list provider wired yet.
     *
     * Throttled at route: throttle:10,1.
     */
    public function newsletter(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        return back()->with('success', 'Subscribed! Watch your inbox for exclusive SANA deals.');
    }
}
