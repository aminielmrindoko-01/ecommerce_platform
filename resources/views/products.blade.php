@extends('layouts.app')

@section('content')

<h1>🛒 All Products</h1>
<p>Browse amazing products from different vendors</p>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-top:20px;">

    <!-- Product Card -->
    <div style="background:white;padding:15px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
<img src="/images/products/phone.jpg"
     style="width:100%;height:120px;object-fit:cover;border-radius:8px;">
        <h3 style="margin:10px 0;">Smartphone</h3>

        <p style="color:green;font-weight:bold;">TSh 350,000</p>

        <p style="font-size:12px;color:gray;">Store: Tech Shop</p>

<a href="/product/1">
    <button style="background:black;color:white;padding:8px 10px;border:none;border-radius:5px;width:100%;">
        View Product
    </button>
</a>
       <a href="/cart/add/1">
    <button style="margin-top:5px;background:orange;color:white;padding:8px 10px;border:none;border-radius:5px;width:100%;">
        Add to Cart
    </button>
</a>
    </div>

    <!-- Product Card -->
    <div style="background:white;padding:15px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
<img src="/images/products/headphones.jpg"
     style="width:100%;height:120px;object-fit:cover;border-radius:10px;">
        <h3 style="margin:10px 0;">Headphones</h3>

        <p style="color:green;font-weight:bold;">TSh 80,000</p>

        <p style="font-size:12px;color:gray;">Store: Sana tech</p>

        <button style="background:black;color:white;padding:8px 10px;border:none;border-radius:5px;width:100%;">
            View Product
        </button>

        <button style="margin-top:5px;background:orange;color:white;padding:8px 10px;border:none;border-radius:5px;width:100%;">
            Add to Cart
        </button>
    </div>
  <!-- Product Card -->
    <div style="background:white;padding:15px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
<img src="/images/products/watch.png"
     style="width:100%;height:120px;object-fit:cover;border-radius:10px;">
        <h3 style="margin:10px 0;">Smartwatch</h3>

        <p style="color:green;font-weight:bold;">TSh 30,000</p>

        <p style="font-size:12px;color:gray;">Store: Audio World</p>

        <button style="background:black;color:white;padding:8px 10px;border:none;border-radius:5px;width:100%;">
            View Product
        </button>

        <button style="margin-top:5px;background:orange;color:white;padding:8px 10px;border:none;border-radius:5px;width:100%;">
            Add to Cart
        </button>
    </div>
      <!-- Product Card -->
    <div style="background:white;padding:15px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
<img src="/images/products/perfume.png"
     style="width:100%;height:120px;object-fit:cover;border-radius:10px;">
        <h3 style="margin:10px 0;">perfume</h3>

        <p style="color:green;font-weight:bold;">TSh 70,000</p>

        <p style="font-size:12px;color:gray;">Store: Niffah cosmetics</p>

        <button style="background:black;color:white;padding:8px 10px;border:none;border-radius:5px;width:100%;">
            View Product
        </button>

        <button style="margin-top:5px;background:orange;color:white;padding:8px 10px;border:none;border-radius:5px;width:100%;">
            Add to Cart
        </button>
    </div>
      <!-- Product Card -->
    <div style="background:white;padding:15px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
<img src="/images/products/shoes.jpg"
     style="width:100%;height:120px;object-fit:cover;border-radius:10px;">
        <h3 style="margin:10px 0;">The Shoes</h3>

        <p style="color:green;font-weight:bold;">TSh 100,000</p>

        <p style="font-size:12px;color:gray;">Store: Nadia store</p>

        <button style="background:black;color:white;padding:8px 10px;border:none;border-radius:5px;width:100%;">
            View Product
        </button>

        <button style="margin-top:5px;background:orange;color:white;padding:8px 10px;border:none;border-radius:5px;width:100%;">
            Add to Cart
        </button>
    </div>
</div>

@endsection