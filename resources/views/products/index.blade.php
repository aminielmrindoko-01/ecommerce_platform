@extends('layouts.app')

@section('content')

<div class="page-header">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h1>🛍️ All Products</h1>
            <p>Discover amazing products from our trusted vendors</p>
        </div>
        @auth
            <a href="{{ route('products.create') }}" class="btn btn-secondary" style="text-decoration:none;">+ Add New Product</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary" style="text-decoration:none;">Sign in to add products</a>
        @endauth
    </div>
</div>

@if($products->isEmpty())
    <div style="background:white;padding:40px;border-radius:12px;text-align:center;margin-top:24px;">
        <p style="font-size:1.1rem;color:#6b7280;">No products available yet. Check back soon!</p>
    </div>
@else
    <div class="products-grid">
        @foreach($products as $product)
            <div class="product-card">
                @if($product->image)
                    <img src="{{ asset('images/'.$product->image) }}" alt="{{ $product->name }}" class="product-image">
                @else
                    <div style="width:100%;height:200px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;font-size:0.9rem;">📦 No Image</div>
                @endif

                <div class="product-info">
                    <h3 class="product-name">{{ $product->name }}</h3>
                    <p style="margin:0 0 10px;color:#4b5563;line-height:1.6;">{{ Str::limit($product->description, 80, '...') }}</p>
                    <div class="product-price">TSh {{ number_format($product->price, 0) }}</div>
                    <div class="product-vendor">{{ $product->vendor->store_name ?? 'Vendor Store' }}</div>
                    <div class="product-stock">
                        @if($product->stock > 0)
                            <span style="color:#059669;">✓ {{ $product->stock }} in stock</span>
                        @else
                            <span style="color:#dc2626;">Out of stock</span>
                        @endif
                    </div>
                    <div class="product-actions">
                        <a href="{{ route('products.show', $product->id) }}" class="btn btn-primary">View Details</a>
                        @if($product->stock > 0)
                            <form method="POST" action="{{ route('cart.add', $product->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary" style="width:100%;border:none;">Add to Cart</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection