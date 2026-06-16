<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('vendor')->latest()->get();
        return view('products.index', compact('products'));
    }

    public function show($id)
    {
        $product = Product::with('vendor')->findOrFail($id);
        return view('products.show', compact('product'));
    }

    public function create()
    {
        $vendors = Vendor::orderBy('store_name')->get();
        return view('products.create', compact('vendors'));
    }

    public function store(ProductRequest $request)
    {
        $data = $request->only(['vendor_id', 'name', 'price', 'stock', 'description']);

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
        return view('products.edit', compact('product', 'vendors'));
    }

    public function update(ProductRequest $request, $id)
    {
        $product = Product::findOrFail($id);

        $product->vendor_id = $request->vendor_id;
        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->stock = $request->stock;

        if ($request->hasFile('image')) {
            // delete old image if exists
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $path = $request->file('image')->store('products', 'public');
            $product->image = $path;
        }

        $product->save();

        return redirect()->route('products.show', $product->id)->with('success', 'Product updated successfully');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return redirect()->route('products.index');
    }
}
