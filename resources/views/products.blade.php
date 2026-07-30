@extends('layouts.app')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:20px;">
    <div>
        <h1>🛒 All Products</h1>
        <p>Browse amazing products from different vendors.</p>
    </div>
</div>

@if($products->isEmpty())
    <div style="background:white;padding:20px;border-radius:12px;">No products are available yet.</div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;">
        @foreach($products as $product)
            <div style="background:white;padding:18px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                @if($product->image)
                    <img src="{{ asset('images/'.$product->image) }}" alt="{{ $product->name }}" style="width:100%;height:170px;object-fit:cover;border-radius:10px;">
                @else
                    <div style="width:100%;height:170px;background:#f0f0f0;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#777;">No image</div>
                @endif

                <h3 style="margin:14px 0 8px;">{{ $product->name }}</h3>
                <p style="margin:0 0 8px;color:#059669;font-weight:700;">TSh {{ number_format($product->price, 2) }}</p>
                <p style="margin:0 0 12px;font-size:13px;color:#555;">Store: {{ $product->vendor->store_name ?? 'Unknown Store' }}</p>
                <div style="display:grid;gap:8px;">
                    <a href="{{ route('products.show', $product->id) }}" style="display:inline-block;text-align:center;background:#111827;color:white;padding:10px 12px;border-radius:8px;text-decoration:none;">View Product</a>
                    <form method="POST" action="{{ route('cart.add', $product->id) }}">
                        @csrf
                        <button type="submit" style="width:100%;background:#f97316;color:white;padding:10px 12px;border:none;border-radius:8px;cursor:pointer;">Add to Cart</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection