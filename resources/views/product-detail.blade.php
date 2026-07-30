@extends('layouts.app')

@section('content')

<div style="display:flex;gap:30px;background:white;padding:20px;border-radius:12px;flex-wrap:wrap;">
    <div style="flex:1;min-width:320px;">
        @if($product->image)
            <img src="{{ asset('images/'.$product->image) }}" alt="{{ $product->name }}" style="width:100%;height:360px;object-fit:cover;border-radius:12px;">
        @else
            <div style="width:100%;height:360px;background:#f0f0f0;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#777;">No image available</div>
        @endif
    </div>

    <div style="flex:1;min-width:320px;">
        <h1 style="margin-top:0;">{{ $product->name }}</h1>
        <p style="font-size:1.4rem;color:#16a34a;font-weight:700;">TSh {{ number_format($product->price, 2) }}</p>
        <p><strong>Stock:</strong> {{ $product->stock }}</p>
        <p><strong>Store:</strong> {{ $product->vendor->store_name ?? 'Unknown Store' }}</p>
        <p style="margin-top:18px;color:#4b5563;line-height:1.7;">{{ $product->description }}</p>

        <form method="POST" action="{{ route('cart.add', $product->id) }}" style="margin-top:24px;">
            @csrf
            <button type="submit" style="width:100%;background:#f97316;color:white;padding:12px 16px;border:none;border-radius:10px;cursor:pointer;">🛒 Add to Cart</button>
        </form>
    </div>
</div>

@endsection