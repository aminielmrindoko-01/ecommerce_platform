<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Vendor-scoped product CRUD. Ownership is always the authenticated vendor store.
 */
class ProductController extends Controller
{
    /**
     * List only products belonging to the authenticated vendor.
     */
    public function index(): View
    {
        $vendor = auth()->user()->vendor;

        $products = Product::with('category')
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->paginate(15);

        return view('vendor.products.index', compact('products', 'vendor'));
    }

    /**
     * Create form for a new store product.
     */
    public function create(): View
    {
        $this->authorize('create', Product::class);

        $categories = Category::orderBy('name')->get();

        return view('vendor.products.create', compact('categories'));
    }

    /**
     * Persist a product owned by the authenticated vendor.
     * vendor_id is assigned server-side and never taken from the request.
     */
    public function store(VendorProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $vendor = auth()->user()->vendor;
        $data = $request->safe()->only(['category_id', 'name', 'brand', 'price', 'stock', 'description']);
        $data['slug'] = Str::slug($request->name).'-'.Str::random(5);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = new Product($data);
        $product->vendor_id = $vendor->id;
        $product->save();

        return redirect()
            ->route('vendor.products.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Edit form — policy enforces ownership (403 for other vendors).
     */
    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $categories = Category::orderBy('name')->get();

        return view('vendor.products.edit', compact('product', 'categories'));
    }

    /**
     * Update owned product fields. Ownership (vendor_id) cannot be changed.
     */
    public function update(VendorProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $product->fill($request->safe()->only(['category_id', 'name', 'brand', 'price', 'stock', 'description']));

        if ($request->hasFile('image')) {
            if ($product->image && ! str_starts_with($product->image, 'http')) {
                Storage::disk('public')->delete($product->image);
            }
            $product->image = $request->file('image')->store('products', 'public');
        }

        $product->save();

        return redirect()
            ->route('vendor.products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Delete an owned product.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('vendor.products.index')
            ->with('success', 'Product deleted.');
    }
}
