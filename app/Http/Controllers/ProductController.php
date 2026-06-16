<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // Show all products (Guest allowed)
    public function index()
    {
        $products = Product::all();
        return view('products.index', compact('products'));
    }

    // Show single product
    public function show($id)
    {
        $product = Product::findOrFail($id);
        return view('products.show', compact('product'));
    }

    // Store product (Admin use later)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required'
        ]);

        Product::create([
            'name' => $request->name,
            'price' => $request->price
        ]);

        return redirect()->back()->with('success', 'Product created successfully');
    }
}