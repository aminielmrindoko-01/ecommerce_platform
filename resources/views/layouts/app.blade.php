<!DOCTYPE html>
<html>
<head>
    <title>Marketplace</title>
    
    <style>
        body { margin:0; font-family: Arial; background:#plum; }

        /* Navbar */
        .navbar {
            background:purple;
            color:plum;
            padding:15px;
            display:flex;
            justify-content:space-between;
        }

        .navbar a {
            color:white;
            margin:0 10px;
            text-decoration:none;
        }

        /* Container */
        .container {
            padding:20px;
        }
        
    </style>
    
</head>
<body >


<div class="navbar">
<img src="/images/logos/logo.png"
     style="height:40px;">
<span style="color:white;font-weight:bold;">SANA MARKET</span>
    <div style="display:flex;gap:15px;align-items:center;">
        <a href="/">Home</a>
        <a href="/products"style="color:white;">Products</a>
        <a href="/categories"style="color:white;"">Categories</a>
        <a href="/vendors"style="color:white;">Vendors</a>
        <a href="/deals"style="color:white;"">Deals</a>
        <a href="/blog"style="color:white;"">Blog</a>
        <a href="/about"style="color:white;"">About</a>
        <a href="/contact"style=''color:white;"">Contact</a>

        
        <a href="/login">Login</a>
        <a href="/register">Register</a>
@php
    $cart = session('cart', []);
    $count = array_sum(array_column($cart, 'quantity'));
@endphp
@php
    $cart = session('cart', []);
    $cartCount = array_sum(array_column($cart, 'quantity'));
@endphp
<a href="/cart" style="
    background:orange;
    padding:6px 10px;
    border-radius:5px;
    color:white;
    text-decoration:none;
">
🛒 Cart ({{ $cartCount }})</a>
  </div>
</div>

<div class="container">
    @yield('content')
</div>

</body>
</html>