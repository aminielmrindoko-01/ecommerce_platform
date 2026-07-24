@extends('layouts.app')

@section('content')

<!-- Hero Section -->
<div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;padding:60px 32px;border-radius:16px;margin-bottom:40px;text-align:center;">
    <h1 style="font-size:2.5rem;margin-bottom:16px;">Welcome to SANA Market</h1>
    <p style="font-size:1.2rem;margin-bottom:24px;opacity:0.95;">Your one-stop online marketplace for quality products from trusted vendors</p>
    <a href="{{ route('products.index') }}" class="btn btn-secondary" style="display:inline-block;text-decoration:none;padding:14px 32px;font-size:1.1rem;">Start Shopping</a>
</div>

<!-- Featured Categories -->
<div style="margin-bottom:48px;">
    <h2 style="font-size:1.8rem;margin-bottom:24px;color:#111827;">Featured Categories</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;">
        <div style="background:white;padding:24px;border-radius:12px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:all 0.3s;cursor:pointer;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
            <div style="font-size:3rem;margin-bottom:12px;">📱</div>
            <h3 style="margin-bottom:8px;color:#111827;">Electronics</h3>
            <p style="color:#6b7280;margin-bottom:16px;">Latest gadgets and tech devices</p>
            <a href="{{ route('categories') }}" style="color:#667eea;text-decoration:none;font-weight:600;">Browse →</a>
        </div>

        <div style="background:white;padding:24px;border-radius:12px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:all 0.3s;cursor:pointer;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
            <div style="font-size:3rem;margin-bottom:12px;">👕</div>
            <h3 style="margin-bottom:8px;color:#111827;">Fashion</h3>
            <p style="color:#6b7280;margin-bottom:16px;">Clothing and accessories</p>
            <a href="{{ route('categories') }}" style="color:#667eea;text-decoration:none;font-weight:600;">Browse →</a>
        </div>

        <div style="background:white;padding:24px;border-radius:12px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:all 0.3s;cursor:pointer;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
            <div style="font-size:3rem;margin-bottom:12px;">🏠</div>
            <h3 style="margin-bottom:8px;color:#111827;">Home</h3>
            <p style="color:#6b7280;margin-bottom:16px;">Furniture and home decor</p>
            <a href="{{ route('categories') }}" style="color:#667eea;text-decoration:none;font-weight:600;">Browse →</a>
        </div>

        <div style="background:white;padding:24px;border-radius:12px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.08);transition:all 0.3s;cursor:pointer;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'">
            <div style="font-size:3rem;margin-bottom:12px;">💄</div>
            <h3 style="margin-bottom:8px;color:#111827;">Beauty</h3>
            <p style="color:#6b7280;margin-bottom:16px;">Beauty and wellness products</p>
            <a href="{{ route('categories') }}" style="color:#667eea;text-decoration:none;font-weight:600;">Browse →</a>
        </div>
    </div>
</div>

<!-- Why Choose Us -->
<div style="background:white;padding:40px;border-radius:12px;margin-bottom:40px;">
    <h2 style="font-size:1.8rem;margin-bottom:32px;color:#111827;text-align:center;">Why Choose SANA Market?</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:32px;">
        <div style="text-align:center;">
            <div style="font-size:3rem;margin-bottom:16px;">✓</div>
            <h3 style="margin-bottom:8px;color:#111827;">Quality Products</h3>
            <p style="color:#6b7280;line-height:1.6;">Curated selection from trusted vendors</p>
        </div>
        <div style="text-align:center;">
            <div style="font-size:3rem;margin-bottom:16px;">🚚</div>
            <h3 style="margin-bottom:8px;color:#111827;">Fast Delivery</h3>
            <p style="color:#6b7280;line-height:1.6;">Quick and reliable shipping options</p>
        </div>
        <div style="text-align:center;">
            <div style="font-size:3rem;margin-bottom:16px;">🛡️</div>
            <h3 style="margin-bottom:8px;color:#111827;">Secure Payment</h3>
            <p style="color:#6b7280;line-height:1.6;">Protected transactions and buyer guarantee</p>
        </div>
        <div style="text-align:center;">
            <div style="font-size:3rem;margin-bottom:16px;">💬</div>
            <h3 style="margin-bottom:8px;color:#111827;">Customer Support</h3>
            <p style="color:#6b7280;line-height:1.6;">24/7 assistance for all your needs</p>
        </div>
    </div>
</div>

<!-- CTA Section -->
<div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;padding:40px;border-radius:12px;text-align:center;">
    <h2 style="font-size:1.8rem;margin-bottom:16px;">Ready to Find What You Need?</h2>
    <p style="font-size:1.1rem;margin-bottom:24px;opacity:0.95;">Browse our extensive collection of products from verified sellers</p>
    <a href="{{ route('products.index') }}" class="btn btn-secondary" style="display:inline-block;text-decoration:none;padding:12px 28px;">View All Products</a>
</div>

@endsection