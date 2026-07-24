<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\Request;

class ShopPageController extends Controller
{
    public function about()
    {
        return view('about');
    }

    public function contact()
    {
        return view('contact');
    }

    public function contactSubmit(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'message' => 'required|string|max:2000',
        ]);

        return back()->with('success', 'Message received — our support team will reply shortly.');
    }

    public function vendors()
    {
        $vendors = Vendor::withCount('products')->orderByDesc('is_verified')->orderBy('store_name')->get();

        return view('vendors', compact('vendors'));
    }

    public function categories()
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->get();

        return view('categories', compact('categories'));
    }

    public function deals()
    {
        $deals = Product::with(['vendor', 'category'])->flashSale()->latest()->paginate(12);
        $flashEndsAt = optional($deals->first()?->flash_ends_at)->getTimestamp() * 1000
            ?: now()->addHours(12)->getTimestamp() * 1000;

        return view('deals', compact('deals', 'flashEndsAt'));
    }

    public function blog()
    {
        return view('blog');
    }

    public function newsletter(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        return back()->with('success', 'Subscribed! Watch your inbox for exclusive SANA deals.');
    }
}
