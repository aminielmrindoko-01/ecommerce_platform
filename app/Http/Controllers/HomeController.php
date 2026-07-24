<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $categories = Category::orderBy('sort_order')->get();

        $featured = Product::with(['vendor', 'category'])
            ->featured()
            ->inStock()
            ->latest()
            ->take(8)
            ->get();

        $flashSales = Product::with(['vendor', 'category'])
            ->flashSale()
            ->inStock()
            ->take(8)
            ->get();

        $bestSellers = Product::with(['vendor', 'category'])
            ->orderByDesc('sold_count')
            ->take(8)
            ->get();

        $newArrivals = Product::with(['vendor', 'category'])
            ->where('is_new', true)
            ->latest()
            ->take(8)
            ->get();

        $trending = Product::with(['vendor', 'category'])
            ->orderByDesc('rating_avg')
            ->orderByDesc('rating_count')
            ->take(8)
            ->get();

        $brands = Product::query()
            ->whereNotNull('brand')
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        $vendors = Vendor::withCount('products')
            ->orderByDesc('is_verified')
            ->take(6)
            ->get();

        $flashEndsAt = optional($flashSales->first()?->flash_ends_at)->getTimestamp() * 1000
            ?: now()->addHours(18)->getTimestamp() * 1000;

        return view('home', compact(
            'categories',
            'featured',
            'flashSales',
            'bestSellers',
            'newArrivals',
            'trending',
            'brands',
            'vendors',
            'flashEndsAt'
        ));
    }
}
