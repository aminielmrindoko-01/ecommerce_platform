<?php
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

public function store(Request $request)


namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductController extends Controller
{
   
    $request->validate([
        'name' => 'required',
        'price' => 'required',
        'stock' => 'required'
    ]);

    Product::create([
        'vendor_id' => Auth::user()->vendor->id,
        'name' => $request->name,
        'price' => $request->price,
        'stock' => $request->stock,
        'description' => $request->description
    ]);

    return back()->with('success', 'Product added successfully!');
} //

