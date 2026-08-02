@extends('layouts.app')
@section('title', 'Vendor dashboard')
@section('content')
@include('vendor._nav')

<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">{{ $vendor->store_name }}</h1>
        <p>Your store performance and fulfillment queue</p>
    </div>
    <a class="btn btn-primary" href="{{ route('vendor.products.create') }}">Add product</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;" class="section">
    <div class="admin-stat"><span>Total products</span><strong>{{ $totalProducts }}</strong></div>
    <div class="admin-stat"><span>Active (in stock)</span><strong>{{ $activeProducts }}</strong></div>
    <div class="admin-stat"><span>Low stock</span><strong>{{ $lowStock }}</strong></div>
    <div class="admin-stat"><span>Orders</span><strong>{{ $totalOrders }}</strong></div>
    <div class="admin-stat"><span>Your sales</span><strong>TSh {{ number_format((float) $totalSales, 0) }}</strong></div>
</div>

<div class="section-head" style="margin-top:1.5rem;">
    <div>
        <h2 class="font-display" style="margin:0;font-size:1.25rem;">Fulfillment</h2>
        <p>Counts are for your order items only</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;" class="section">
    <div class="admin-stat"><span>Pending</span><strong>{{ $pendingFulfillment }}</strong></div>
    <div class="admin-stat"><span>Confirmed</span><strong>{{ $confirmedFulfillment }}</strong></div>
    <div class="admin-stat"><span>Processing</span><strong>{{ $processingFulfillment }}</strong></div>
    <div class="admin-stat"><span>Shipped</span><strong>{{ $shippedFulfillment }}</strong></div>
    <div class="admin-stat"><span>Delivered</span><strong>{{ $deliveredFulfillment }}</strong></div>
    <div class="admin-stat"><span>Cancelled</span><strong>{{ $cancelledFulfillment }}</strong></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;" class="section">
    <div class="panel">
        <h2 style="margin-top:0;font-size:1.1rem;">Latest products</h2>
        @forelse($recentProducts as $product)
            <div style="padding:.65rem 0;border-bottom:1px solid var(--color-border);">
                <strong>{{ $product->name }}</strong>
                <div style="color:var(--color-ink-muted);font-size:.9rem;">TSh {{ number_format($product->price, 0) }} · Stock {{ $product->stock }}</div>
            </div>
        @empty
            <p style="color:var(--color-ink-muted);">No products yet.</p>
        @endforelse
    </div>
    <div class="panel">
        <h2 style="margin-top:0;font-size:1.1rem;">Recent orders (your items)</h2>
        @forelse($recentOrders as $order)
            <div style="padding:.65rem 0;border-bottom:1px solid var(--color-border);">
                <a href="{{ route('vendor.orders.show', $order) }}"><strong>{{ $order->order_number ?? '#'.$order->id }}</strong></a>
                <div style="color:var(--color-ink-muted);font-size:.9rem;">Order {{ ucfirst($order->status) }} · {{ $order->items->count() }} item(s)</div>
            </div>
        @empty
            <p style="color:var(--color-ink-muted);">No orders containing your products yet.</p>
        @endforelse
    </div>
</div>
@endsection
