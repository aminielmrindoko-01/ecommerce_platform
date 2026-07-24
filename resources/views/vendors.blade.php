@extends('layouts.app')
@section('title', 'Sellers')
@section('content')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Top sellers</h1>
        <p>Verified and rising stores on SANA Market</p>
    </div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
@foreach($vendors as $vendor)
<article class="panel">
    <div style="display:flex;gap:.85rem;align-items:center;">
        <img src="{{ $vendor->logo ?? 'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?auto=format&fit=crop&w=120&q=80' }}" alt="" width="56" height="56" style="border-radius:12px;object-fit:cover;">
        <div>
            <strong>{{ $vendor->store_name }}</strong>
            @if($vendor->is_verified)<span class="badge badge-new">Verified</span>@endif
            <div style="color:var(--color-ink-muted);font-size:.9rem;">★ {{ number_format($vendor->rating_avg,1) }} · {{ $vendor->location }}</div>
        </div>
    </div>
    <p style="color:var(--color-ink-muted);line-height:1.6;">{{ $vendor->description }}</p>
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:.9rem;color:var(--color-ink-muted);">{{ $vendor->products_count }} products</span>
        <a class="btn btn-ghost" href="{{ route('products.index') }}">Browse</a>
    </div>
</article>
@endforeach
</div>
@endsection
