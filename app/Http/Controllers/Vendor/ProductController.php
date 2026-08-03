<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Vendor-scoped product CRUD. Ownership is always the authenticated vendor store.
 */
class ProductController extends Controller
{
    public function __construct(
        protected ProductCatalogService $catalog,
    ) {}

    public function index(): View
    {
        $vendor = auth()->user()->vendor;

        $products = Product::with('category')
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->paginate(15);

        return view('vendor.products.index', compact('products', 'vendor'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        $categories = Category::orderBy('name')->get();

        return view('vendor.products.create', compact('categories'));
    }

    public function store(VendorProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $vendor = auth()->user()->vendor;

        try {
            // Ignore client vendor_id entirely — force authenticated vendor.
            $this->catalog->create(
                $request->safe()->only(['category_id', 'name', 'brand', 'price', 'stock', 'description', 'sku'])
                    + [
                        'image' => $request->file('image'),
                        'status' => 'pending_review',
                    ],
                $request->user(),
                $vendor,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['product' => $e->getMessage()]);
        }

        return redirect()
            ->route('vendor.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $categories = Category::orderBy('name')->get();

        return view('vendor.products.edit', compact('product', 'categories'));
    }

    public function update(VendorProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        try {
            $data = $request->safe()->only(['category_id', 'name', 'brand', 'price', 'description', 'sku'])
                + ['image' => $request->file('image')];

            if ($request->user()->hasPermission('inventory.adjust')) {
                $data['stock'] = $request->input('stock');
            }

            $this->catalog->update($product, $data, $request->user(), allowVendorIdChange: false);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['product' => $e->getMessage()]);
        }

        return redirect()
            ->route('vendor.products.index')
            ->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $this->catalog->archive($product, request()->user());

        return redirect()
            ->route('vendor.products.index')
            ->with('success', 'Product archived.');
    }
}
