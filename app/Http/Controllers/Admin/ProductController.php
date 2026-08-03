<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminProductRequest;
use App\Http\Requests\Admin\UpdateAdminProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Enterprise admin product operations (list/search/CRUD/lifecycle).
 */
class ProductController extends Controller
{
    public function __construct(
        protected ProductCatalogService $catalog,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()->with(['vendor', 'category']);

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('brand', 'like', '%'.$search.'%');
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($vendorId = $request->get('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }
        if ($request->get('stock') === 'low') {
            $query->whereColumn('stock', '<=', 'reorder_level')->where('stock', '>', 0);
        }
        if ($request->get('stock') === 'out') {
            $query->where('stock', '<=', 0);
        }

        $sort = $request->get('sort', 'latest');
        match ($sort) {
            'name' => $query->orderBy('name'),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'stock' => $query->orderBy('stock'),
            default => $query->latest(),
        };

        $products = $query->paginate(20)->withQueryString();
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $vendors = Vendor::query()->orderBy('store_name')->get(['id', 'store_name']);

        return view('admin.products.index', compact('products', 'categories', 'vendors'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('admin.products.create', [
            'vendors' => Vendor::query()->orderBy('store_name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'statuses' => ProductCatalogService::STATUSES,
        ]);
    }

    public function store(StoreAdminProductRequest $request): RedirectResponse
    {
        try {
            $product = $this->catalog->create(
                $request->safe()->all() + ['image' => $request->file('image')],
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['product' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Product created.');
    }

    public function show(Product $product): View
    {
        abort_unless($product->exists, 404);
        $product->load(['vendor', 'category', 'inventoryMovements' => fn ($q) => $q->latest('created_at')->limit(10)]);

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('admin.products.edit', [
            'product' => $product,
            'vendors' => Vendor::query()->orderBy('store_name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'statuses' => ProductCatalogService::STATUSES,
        ]);
    }

    public function update(UpdateAdminProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        try {
            $data = $request->safe()->all() + ['image' => $request->file('image')];
            // Drop stock if actor cannot adjust inventory (prevent silent mass change).
            if (! $request->user()->hasPermission('inventory.adjust')) {
                unset($data['stock']);
            }
            $product = $this->catalog->update($product, $data, $request->user(), allowVendorIdChange: true);

            if ($request->filled('status') && $request->string('status') !== $product->status) {
                $product = $this->catalog->setStatus($product, (string) $request->input('status'), $request->user());
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['product' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Product updated.');
    }

    public function publish(Product $product): RedirectResponse
    {
        $this->authorize('publish', $product);
        try {
            $this->catalog->publish($product, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Product published.');
    }

    public function unpublish(Product $product): RedirectResponse
    {
        $this->authorize('unpublish', $product);
        try {
            $this->catalog->unpublish($product, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Product unpublished.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $this->catalog->archive($product, request()->user());

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product archived.');
    }
}
