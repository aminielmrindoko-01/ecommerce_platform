<?php

/**
 * |--------------------------------------------------------------------------
 * | Catalog browsing & seller product CRUD
 * |--------------------------------------------------------------------------
 * | Public index/show with filters; authenticated create/update/destroy.
 * | Reviews recalculate denormalized rating_avg / rating_count on the product.
 */

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\Vendor;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Product listing, detail, CRUD, reviews, and Q&A.
 *
 * @package App\Http\Controllers
 */
class ProductController extends Controller
{
    public function __construct(
        protected ProductCatalogService $catalog,
    ) {}

    /**
     * Filtered/paginated catalog. Eager-loads vendor + category to avoid N+1 in cards.
     *
     * Query params: q, category (slug), brand, min_price, max_price, rating, in_stock, sort.
     * Successful searches are remembered in session (`recent_searches`, max 8).
     */
    public function index(Request $request): View
    {
        $query = Product::with(['vendor', 'category'])->published();

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

    /**
     * Product detail with related (same category) and frequently-bought-together picks.
     *
     * @param  int|string  $id
     */
    public function show($id): View
    {
        $product = Product::with(['vendor', 'category', 'reviews' => fn ($q) => $q->latest()->take(10), 'questions'])
            ->published()
            ->findOrFail($id);

        $related = Product::with(['vendor', 'category'])
            ->published()
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->take(4)
            ->get();

        // FBT is currently random in-stock products (not co-purchase analytics).
        $fbt = Product::with(['vendor', 'category'])
            ->where('id', '!=', $product->id)
            ->inStock()
            ->inRandomOrder()
            ->take(3)
            ->get();

        return view('products.show', compact('product', 'related', 'fbt'));
    }

    /**
     * Create-product form (admin only at route + policy).
     */
    public function create(): View
    {
        $this->authorize('create', Product::class);

        $vendors = Vendor::orderBy('store_name')->get();
        $categories = Category::orderBy('name')->get();

        return view('products.create', compact('vendors', 'categories'));
    }

    /**
     * Persist a new product; optional image stored on the public disk under products/.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        try {
            $product = $this->catalog->create(
                $request->safe()->all() + [
                    'image' => $request->file('image'),
                    'status' => 'published',
                ],
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['product' => $e->getMessage()]);
        }

        return redirect()->route('admin.products.show', $product)->with('success', 'Product created successfully');
    }

    /**
     * Edit form for an existing product.
     *
     * @param  int|string  $id
     */
    public function edit($id): View
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);

        $vendors = Vendor::orderBy('store_name')->get();
        $categories = Category::orderBy('name')->get();

        return view('products.edit', compact('product', 'vendors', 'categories'));
    }

    /**
     * Update product fields via catalog service (audited).
     *
     * @param  int|string  $id
     */
    public function update(ProductRequest $request, $id): RedirectResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);

        try {
            $data = $request->safe()->all() + ['image' => $request->file('image')];
            if (! $request->user()->hasPermission('inventory.adjust')) {
                unset($data['stock']);
            }
            $product = $this->catalog->update($product, $data, $request->user(), allowVendorIdChange: true);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['product' => $e->getMessage()]);
        }

        return redirect()->route('admin.products.show', $product)->with('success', 'Product updated successfully');
    }

    /**
     * Archive (soft-delete) a product.
     *
     * @param  int|string  $id
     */
    public function destroy($id): RedirectResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('delete', $product);
        $this->catalog->archive($product, request()->user());

        return redirect()->route('admin.products.index')->with('success', 'Product archived.');
    }

    /**
     * Store a review and refresh denormalized rating aggregates on the product.
     *
     * Guests may review (user_id nullable); author_name falls back to Guest.
     *
     * @param  int|string  $id
     */
    public function storeReview(Request $request, $id): RedirectResponse
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

    /**
     * Store a product question (answer left null for seller/admin follow-up).
     *
     * @param  int|string  $id
     */
    public function storeQuestion(Request $request, $id): RedirectResponse
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
