<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SANA Market - Online Marketplace</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f8fafc; 
            color: #1f2937;
        }
        
        /* Navbar Styling */
        .navbar { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 16px 24px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            flex-wrap: wrap; 
            gap: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .navbar .brand { 
            font-weight: 700; 
            font-size: 1.3rem;
            letter-spacing: 0.5px;
        }
        .navbar .brand a { color: white; text-decoration: none; }
        .navbar .nav-links { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 4px; 
            align-items: center;
        }
        .navbar a, .navbar button { 
            color: white; 
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .navbar button { 
            background: #f59e0b; 
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        .navbar button:hover { background: #d97706; }
        
        /* Container */
        .container { 
            padding: 32px 24px; 
            max-width: 1400px; 
            margin: 0 auto; 
        }
        
        /* Header */
        .page-header {
            margin-bottom: 32px;
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 8px;
            color: #111827;
        }
        .page-header p {
            color: #6b7280;
            font-size: 1.05rem;
        }
        
        /* Product Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        
        /* Product Card */
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
            display: block;
        }
        .product-info {
            padding: 16px;
        }
        .product-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #111827;
            line-height: 1.4;
        }
        .product-price {
            font-size: 1.3rem;
            color: #059669;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .product-vendor {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 12px;
        }
        .product-stock {
            font-size: 0.85rem;
            color: #4b5563;
            margin-bottom: 12px;
        }
        .product-actions {
            display: grid;
            gap: 8px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #f97316;
            color: white;
        }
        .btn-secondary:hover {
            background: #ea580c;
            transform: translateY(-2px);
        }
        
        /* Product Detail */
        .product-detail {
            background: white;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .product-detail-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 24px;
        }
        .product-detail-image {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border-radius: 12px;
        }
        .product-detail-info h1 {
            font-size: 1.8rem;
            margin-bottom: 16px;
            color: #111827;
        }
        .detail-price {
            font-size: 2rem;
            color: #059669;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .detail-meta {
            background: #f3f4f6;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .detail-meta p {
            margin: 8px 0;
            font-size: 0.95rem;
        }
        .detail-meta strong { color: #111827; }
        .detail-description {
            line-height: 1.8;
            color: #4b5563;
            margin-bottom: 24px;
            font-size: 1.05rem;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #111827;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-error {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 6px;
        }
        
        /* Cart & Checkout */
        .cart-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 24px;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 2px solid #e5e7eb;
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .product-detail-layout {
                grid-template-columns: 1fr;
            }
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
            .page-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="brand"><a href="{{ route('home') }}">🏪 SANA MARKET</a></div>
        <div class="nav-links">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('products.index') }}">Products</a>
            <a href="{{ route('categories') }}">Categories</a>
            <a href="{{ route('deals') }}">Deals</a>
            <a href="{{ route('cart.index') }}">🛒 Cart</a>
            @guest
                <a href="{{ route('login') }}">Login</a>
                <a href="{{ route('register') }}">Register</a>
            @else
                <a href="{{ route('profile') }}">Profile</a>
            @if(auth()->check() && auth()->user()->role === 'admin')
                <a href="{{ route('admin.dashboard') }}">Admin</a>
            @endif
                <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                    @csrf
                    <button type="submit">Logout</button>
                </form>
            @endguest
        </div>
    </div>

    <div class="container">
        @if(session('success'))
            <div style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:16px;border-radius:12px;margin-bottom:24px;">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:16px;border-radius:12px;margin-bottom:24px;">
                {{ session('error') }}
            </div>
        @endif
        @yield('content')
    </div>
    <footer style="background:#0f172a;color:#cbd5e1;padding:28px 24px;margin-top:48px;">
        <div style="max-width:1400px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
                <strong>🏪 SANA MARKET</strong>
                <p style="margin-top:6px;color:#94a3b8;">Nationwide marketplace — secure payments, fast delivery.</p>
            </div>
            <div style="color:#94a3b8;">
                <div style="margin-bottom:6px;">Contact: support@sana.com</div>
                <div>© {{ date('Y') }} SANA Market. All rights reserved.</div>
            </div>
        </div>
    </footer>
</body>
</html>