@extends('layouts.app')
@section('title', 'Order '.$order->order_number)
@section('content')
@include('account._nav')
<div class="panel">
    <h1 class="font-display" style="margin-top:0;">{{ $order->order_number ?? 'Order #'.$order->id }}</h1>
    @php $payment = $order->latestPaymentTransaction; @endphp
    <p style="color:var(--color-ink-muted);">
        Placed {{ $order->created_at->format('M d, Y H:i') }}
        · Order status: <strong>{{ ucfirst($order->status) }}</strong>
        · Payment: <strong>{{ ucfirst($order->payment_status ?? 'pending') }}</strong>
        · Fulfillment: <strong>{{ $fulfillmentSummaryLabel }}</strong>
    </p>
    <div style="display:grid;gap:.4rem;max-width:420px;margin:1rem 0;">
        <div style="display:flex;justify-content:space-between;"><span>Payment method</span><strong>{{ strtoupper(str_replace('_',' ', $order->payment_method ?? 'n/a')) }}</strong></div>
        <div style="display:flex;justify-content:space-between;"><span>Payment status</span><strong>{{ ucfirst($order->payment_status ?? 'pending') }}</strong></div>
        @if($payment)
            <div style="display:flex;justify-content:space-between;"><span>Payment reference</span><strong>{{ $payment->reference }}</strong></div>
        @endif
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

@php
    $steps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
@endphp

@foreach($itemsByVendor as $group)
    <div class="panel" style="margin-top:1rem;">
        <h2 style="margin-top:0;font-size:1.1rem;">{{ $group['store_name'] }}</h2>
        @foreach($group['items'] as $item)
            @php
                $status = $item->fulfillment_status ?: 'pending';
                $currentIndex = array_search($status, $steps, true);
            @endphp
            <div style="padding:.85rem 0;border-bottom:1px solid var(--color-border);">
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;">
                    <div>
                        <strong>{{ $item->product->name ?? 'Product' }}</strong>
                        <div style="color:var(--color-ink-muted);font-size:.9rem;">
                            Vendor: {{ $group['store_name'] }} · Qty {{ $item->quantity }} · {{ money($item->price) }} each
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div><strong>{{ money($item->price * $item->quantity) }}</strong></div>
                        <span class="chip" style="margin-top:.35rem;display:inline-block;">{{ ucfirst($status) }}</span>
                    </div>
                </div>

                @if($status === 'cancelled')
                    <p style="margin:.75rem 0 0;color:var(--color-ink-muted);font-size:.9rem;">Cancelled</p>
                @else
                    <ol style="list-style:none;padding:0;margin:.85rem 0 0;display:flex;flex-wrap:wrap;gap:.35rem .55rem;font-size:.82rem;">
                        @foreach($steps as $i => $step)
                            @php
                                $done = $currentIndex !== false && $i <= $currentIndex;
                                $current = $currentIndex !== false && $i === $currentIndex;
                            @endphp
                            <li style="padding:.25rem .55rem;border:1px solid var(--color-border);border-radius:999px;{{ $done ? 'background:var(--color-surface);font-weight:600;' : 'color:var(--color-ink-muted);' }}{{ $current ? 'outline:2px solid var(--color-accent, #1a5cff);' : '' }}">
                                {{ $done ? '✓ ' : '' }}{{ ucfirst($step) }}
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endforeach
    </div>
@endforeach
@endsection
