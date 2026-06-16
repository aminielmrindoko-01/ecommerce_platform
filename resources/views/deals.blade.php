@extends('layouts.app')

@section('content')

<div class="page-header">
    <h1>🔥 Current Deals</h1>
    <p>Save more with curated promotions and limited-time offers from trusted vendors.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;">
    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h2 style="margin-bottom:10px;color:#111827;">Electronics Flash Sale</h2>
        <p style="color:#6b7280;line-height:1.7;">Up to 20% off selected headphones and smartphones for a short time only.</p>
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="text-decoration:none;display:inline-block;margin-top:18px;">Shop Electronics</a>
    </div>

    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h2 style="margin-bottom:10px;color:#111827;">Home & Living Offer</h2>
        <p style="color:#6b7280;line-height:1.7;">Enjoy special pricing on furniture bundles and home accessories while stocks last.</p>
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="text-decoration:none;display:inline-block;margin-top:18px;">Shop Home</a>
    </div>

    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h2 style="margin-bottom:10px;color:#111827;">Beauty Essentials</h2>
        <p style="color:#6b7280;line-height:1.7;">Discover exclusive beauty bundles and wellness products with great savings.</p>
        <a href="{{ route('products.index') }}" class="btn btn-secondary" style="text-decoration:none;display:inline-block;margin-top:18px;">Shop Beauty</a>
    </div>
</div>

@endsection