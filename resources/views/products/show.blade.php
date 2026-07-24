@extends('layouts.app')

@section('content')

<div class="product-detail">
    <div class="product-detail-layout">
        <div>
            @if($product->image)
                <img src="{{ asset('images/'.$product->image) }}" alt="{{ $product->name }}" class="product-detail-image">
            @else
                <div style="width:100%;height:500px;background:#f0f0f0;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#999;">📦 No image available</div>
            @endif
        </div>

        <div class="product-detail-info">
            <h1>{{ $product->name }}</h1>
            
            <div class="detail-price">TSh {{ number_format($product->price, 0) }}</div>

            <div class="detail-meta">
                <p><strong>Vendor:</strong> {{ $product->vendor->store_name ?? 'Vendor Store' }}</p>
                <p><strong>Email:</strong> {{ $product->vendor->email ?? 'N/A' }}</p>
                <p><strong>Stock Available:</strong> 
                    @if($product->stock > 0)
                        <span style="color:#059669;font-weight:600;">{{ $product->stock }} units</span>
                    @else
                        <span style="color:#dc2626;font-weight:600;">Out of Stock</span>
                    @endif
                </p>
            </div>

            <div class="detail-description">
                {{ $product->description }}
            </div>

            @if($product->stock > 0)
                <form method="POST" action="{{ route('cart.add', $product->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary" style="width:100%;padding:14px;font-size:1.1rem;border:none;">🛒 Add to Cart</button>
                </form>
            @else
                <div style="padding:14px;background:#fee2e2;color:#991b1b;border-radius:8px;text-align:center;font-weight:600;">
                    Out of Stock
                </div>
            @endif

            @auth
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;">
                    <a href="{{ route('products.edit', $product->id) }}" class="btn btn-primary" style="text-decoration:none;text-align:center;">Edit Product</a>
                    <form method="POST" action="{{ route('products.destroy', $product->id) }}" style="display:flex;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn" style="width:100%;background:#dc2626;color:white;border:none;cursor:pointer;" onclick="return confirm('Delete this product?');">Delete</button>
                    </form>
                </div>
            @endauth
        </div>
    </div>

    <div style="margin-top:32px;padding-top:32px;border-top:2px solid #e5e7eb;">
        <h2 style="margin-bottom:16px;color:#111827;">Product Details</h2>
        <div style="background:#f3f4f6;padding:20px;border-radius:8px;line-height:1.8;">
            {{ $product->description }}
        </div>
    </div>

    <div style="margin-top:24px;">
        <a href="{{ route('products.index') }}" class="btn btn-primary" style="text-decoration:none;">← Back to Products</a>
    </div>
</div>

@endsection