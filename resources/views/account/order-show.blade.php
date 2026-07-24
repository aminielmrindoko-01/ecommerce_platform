@extends('layouts.app')
@section('title', 'Order '.$order->order_number)
@section('content')
@include('account._nav')
<div class="panel">
    <h1 class="font-display" style="margin-top:0;">{{ $order->order_number ?? 'Order #'.$order->id }}</h1>
    <p style="color:var(--color-ink-muted);">Placed {{ $order->created_at->format('M d, Y H:i') }} · Status: <strong>{{ ucfirst($order->status) }}</strong></p>
    <div style="display:grid;gap:.4rem;max-width:420px;margin:1rem 0;">
        <div style="display:flex;justify-content:space-between;"><span>Payment</span><strong>{{ strtoupper(str_replace('_',' ', $order->payment_method ?? 'n/a')) }}</strong></div>
        <div style="display:flex;justify-content:space-between;"><span>Shipping</span><strong>{{ ucfirst($order->shipping_method ?? 'standard') }}</strong></div>
        <div style="display:flex;justify-content:space-between;"><span>Total</span><strong>TSh {{ number_format($order->total_price, 0) }}</strong></div>
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
<div class="panel" style="padding:0;margin-top:1rem;">
    @foreach($order->items as $item)
        <div style="display:flex;justify-content:space-between;padding:1rem;border-bottom:1px solid var(--color-border);">
            <span>{{ $item->product->name ?? 'Product' }} × {{ $item->quantity }}</span>
            <strong>TSh {{ number_format($item->price * $item->quantity, 0) }}</strong>
        </div>
    @endforeach
</div>
@endsection
