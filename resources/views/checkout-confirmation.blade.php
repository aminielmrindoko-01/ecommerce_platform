@extends('layouts.app')

@section('title', 'Order confirmed')

@section('content')
<div class="panel" style="text-align:center;padding:2.5rem 1.5rem;max-width:720px;margin:0 auto;">
    <div class="badge badge-stock" style="margin-bottom:1rem;">Order placed</div>
    <h1 class="font-display" style="margin:0 0 .5rem;">Thank you!</h1>
    <p style="color:var(--color-ink-muted);margin:0 0 1.25rem;">Order <strong>{{ $order->order_number ?? '#'.$order->id }}</strong> is confirmed. Complete payment via <strong>{{ strtoupper(str_replace('_', ' ', $order->payment_method ?? 'selected method')) }}</strong>. Gateways are not charged automatically.</p>
    <div style="display:grid;gap:.45rem;text-align:left;max-width:420px;margin:0 auto 1.5rem;font-size:.95rem;">
        <div style="display:flex;justify-content:space-between;"><span>Order status</span><strong>{{ ucfirst($order->status) }}</strong></div>
        <div style="display:flex;justify-content:space-between;"><span>Payment status</span><strong>{{ ucfirst($order->payment_status ?? 'pending') }}</strong></div>
        <div style="display:flex;justify-content:space-between;"><span>Total</span><strong>{{ money($order->total_price) }}</strong></div>
        <div style="display:flex;justify-content:space-between;"><span>Shipping</span><strong>{{ ucfirst($order->shipping_method ?? 'standard') }}</strong></div>
    </div>
    <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;">
        <a class="btn btn-primary" href="{{ route('account.orders.show', $order) }}">View order</a>
        <a class="btn btn-ghost" href="{{ route('products.index') }}">Continue shopping</a>
    </div>
</div>

<section class="section" style="margin-top:2rem;max-width:720px;margin-inline:auto;">
    <h2>Items</h2>
    <div class="panel" style="padding:0;">
        @foreach($order->items as $item)
            <div style="display:flex;justify-content:space-between;padding:1rem;border-bottom:1px solid var(--color-border);">
                <span>{{ $item->product->name ?? 'Product' }} × {{ $item->quantity }}</span>
                <strong>{{ money($item->price * $item->quantity) }}</strong>
            </div>
        @endforeach
    </div>
</section>
@endsection
