@extends('layouts.app')

@section('content')

<div class="page-header">
    <h1>📂 Shop by Category</h1>
    <p>Browse our wide selection of quality products</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-top:32px;">

    <div style="background:white;padding:32px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);text-align:center;transition:all 0.3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
        <div style="font-size:4rem;margin-bottom:16px;">📱</div>
        <h2 style="margin-bottom:12px;color:#111827;">Electronics</h2>
        <p style="color:#6b7280;margin-bottom:20px;line-height:1.6;">Latest phones, laptops, headphones and more gadgets</p>
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="text-decoration:none;display:inline-block;">Browse Electronics</a>
    </div>

    <div style="background:white;padding:32px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);text-align:center;transition:all 0.3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
        <div style="font-size:4rem;margin-bottom:16px;">👕</div>
        <h2 style="margin-bottom:12px;color:#111827;">Fashion</h2>
        <p style="color:#6b7280;margin-bottom:20px;line-height:1.6;">Trendy clothing, shoes, and accessories for everyone</p>
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="text-decoration:none;display:inline-block;">Browse Fashion</a>
    </div>

    <div style="background:white;padding:32px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);text-align:center;transition:all 0.3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
        <div style="font-size:4rem;margin-bottom:16px;">🏠</div>
        <h2 style="margin-bottom:12px;color:#111827;">Home & Living</h2>
        <p style="color:#6b7280;margin-bottom:20px;line-height:1.6;">Furniture, decor, and everything for your home</p>
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="text-decoration:none;display:inline-block;">Browse Home</a>
    </div>

    <div style="background:white;padding:32px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);text-align:center;transition:all 0.3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
        <div style="font-size:4rem;margin-bottom:16px;">💄</div>
        <h2 style="margin-bottom:12px;color:#111827;">Beauty & Wellness</h2>
        <p style="color:#6b7280;margin-bottom:20px;line-height:1.6;">Beauty products, skincare, and wellness items</p>
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="text-decoration:none;display:inline-block;">Browse Beauty</a>
    </div>

    <div style="background:white;padding:32px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);text-align:center;transition:all 0.3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
        <div style="font-size:4rem;margin-bottom:16px;">🎁</div>
        <h2 style="margin-bottom:12px;color:#111827;">Special Offers</h2>
        <p style="color:#6b7280;margin-bottom:20px;line-height:1.6;">Exclusive deals and limited-time promotions</p>
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="text-decoration:none;display:inline-block;">View Deals</a>
    </div>

    <div style="background:white;padding:32px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);text-align:center;transition:all 0.3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
        <div style="font-size:4rem;margin-bottom:16px;">📦</div>
        <h2 style="margin-bottom:12px;color:#111827;">All Products</h2>
        <p style="color:#6b7280;margin-bottom:20px;line-height:1.6;">Complete selection of all available products</p>
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="text-decoration:none;display:inline-block;">See All</a>
    </div>

</div>

@endsection