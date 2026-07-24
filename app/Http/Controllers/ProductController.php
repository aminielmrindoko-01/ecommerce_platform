<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['vendor', 'category']);

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });

            $recent = collect(session('recent_searches', []));
            $recent = $recent->prepend($search)->unique()->take(8)->values();
            session(['recent_searches' => $recent->all()]);
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        if ($request->filled('rating')) {
            $query->where('rating_avg', '>=', (float) $request->rating);
        }

        if ($request->boolean('in_stock')) {
            $query->inStock();
        }

        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('rating_avg'),
            'popular' => $query->orderByDesc('sold_count'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();

        $categories = Category::withCount('products')->orderBy('sort_order')->get();
        $brands = Product::whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand');
        $popularSearches = ['iPhone', 'Samsung', 'MacBook', 'Nike', 'Headphones'];
        $recentSearches = session('recent_searches', []);

        return view('products.index', compact(
            'products',
            'categories',
            'brands',
            'popularSearches',
            'recentSearches',
            'sort'
        ));
    }

    public function show($id)
    {
        $product = Product::with(['vendor', 'category', 'reviews' => fn ($q) => $q->latest()->take(10), 'questions'])
            ->findOrFail($id);

        $related = Product::with(['vendor', 'category'])
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->take(4)
            ->get();

        $fbt = Product::with(['vendor', 'category'])
            ->where('id', '!=', $product->id)
            ->inStock()
            ->inRandomOrder()
            ->take(3)
            ->get();

        return view('products.show', compact('product', 'related', 'fbt'));
    }

    public function create()
    {
        $vendors = Vendor::orderBy('store_name')->get();
        $categories = Category::orderBy('name')->get();

        return view('products.create', compact('vendors', 'categories'));
    }

    public function store(ProductRequest $request)
    {
        $data = $request->only(['vendor_id', 'category_id', 'name', 'brand', 'price', 'stock', 'description']);
        $data['slug'] = Str::slug($request->name).'-'.Str::random(5);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = $path;
        }

        Product::create($data);

        return redirect()->route('products.index')->with('success', 'Product created successfully');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $vendors = Vendor::orderBy('store_name')->get();
        $categories = Category::orderBy('name')->get();

        return view('products.edit', compact('product', 'vendors', 'categories'));
    }

    public function update(ProductRequest $request, $id)
    {
        $product = Product::findOrFail($id);

        $product->fill($request->only(['vendor_id', 'category_id', 'name', 'brand', 'description', 'price', 'stock']));

        if ($request->hasFile('image')) {
            if ($product->image && ! str_starts_with($product->image, 'http')) {
                Storage::disk('public')->delete($product->image);
            }
            $product->image = $request->file('image')->store('products', 'public');
        }

        $product->save();

        return redirect()->route('products.show', $product->id)->with('success', 'Product updated successfully');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted.');
    }

    public function storeReview(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:120',
            'body' => 'required|string|max:2000',
            'author_name' => 'nullable|string|max:80',
        ]);

        Review::create([
            'product_id' => $product->id,
            'user_id' => auth()->id(),
            'author_name' => $data['author_name'] ?? auth()->user()?->name ?? 'Guest',
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
        ]);

        $product->rating_avg = round($product->reviews()->avg('rating'), 2);
        $product->rating_count = $product->reviews()->count();
        $product->save();

        return back()->with('success', 'Thanks for your review!');
    }

    public function storeQuestion(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'question' => 'required|string|max:1000',
            'author_name' => 'nullable|string|max:80',
        ]);

        ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => auth()->id(),
            'author_name' => $data['author_name'] ?? auth()->user()?->name ?? 'Guest',
            'question' => $data['question'],
            'answer' => null,
        ]);

        return back()->with('success', 'Your question was submitted. Sellers typically reply within 24 hours.');
    }
}
