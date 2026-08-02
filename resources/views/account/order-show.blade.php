@extends('layouts.app')
@section('title', 'Order '.$order->order_number)
@section('content')
@include('account._nav')
<div class="panel">
    <h1 class="font-display" style="margin-top:0;">{{ $order->order_number ?? 'Order #'.$order->id }}</h1>
    <p style="color:var(--color-ink-muted);">Placed {{ $order->created_at->format('M d, Y H:i') }} · Order status: <strong>{{ ucfirst($order->status) }}</strong></p>
    <div style="display:grid;gap:.4rem;max-width:420px;margin:1rem 0;">
        <div style="display:flex;justify-content:space-between;"><span>Payment</span><strong>{{ strtoupper(str_replace('_',' ', $order->payment_method ?? 'n/a')) }}</strong></div>
        <div style="display:flex;justify-content:space-between;"><span>Shipping</span><strong>{{ ucfirst($order->shipping_method ?? 'standard') }}</strong></div>
        <div style="display:flex;justify-content:space-between;"><span>Total</span><strong>{{ money($order->total_price) }}</strong></div>
    </div>
    @if($order->shipping_address)
        <div class="panel" style="background:var(--color-surface);">
            <strong>Ship to</strong>
            <p style="margin:.4rem 0 0;line-height:1.6;">
                {{ $order->shipping_address['full_name'] ?? '' }}<br>
                {{ $order->shipping_address['line1'] ?? '' }} {{ $order->shipping_address['line2'] ?? '' }}<br>
                {{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['region'] ?? '' }}<br>
                {{ $order->shipping_address['phone'] ?? '' }}
            </p>
        </div>
    @endif
</div>

@foreach($itemsByVendor as $group)
    <div class="panel" style="margin-top:1rem;">
        <h2 style="margin-top:0;font-size:1.1rem;">{{ $group['store_name'] }}</h2>
        @foreach($group['items'] as $item)
            <div style="display:flex;justify-content:space-between;gap:1rem;padding:.85rem 0;border-bottom:1px solid var(--color-border);align-items:center;">
                <div>
                    <strong>{{ $item->product->name ?? 'Product' }}</strong>
                    <div style="color:var(--color-ink-muted);font-size:.9rem;">Qty {{ $item->quantity }} · {{ money($item->price) }} each</div>
                </div>
                <div style="text-align:right;">
                    <div><strong>{{ money($item->price * $item->quantity) }}</strong></div>
                    <span class="chip" style="margin-top:.35rem;display:inline-block;">{{ ucfirst($item->fulfillment_status ?? 'pending') }}</span>
                </div>
            </div>
        @endforeach
    </div>
@endforeach
@endsection
