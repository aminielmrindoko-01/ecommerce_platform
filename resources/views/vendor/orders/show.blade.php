@extends('layouts.app')
@section('title', 'Order '.$order->order_number)
@section('content')
@include('vendor._nav')

<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">{{ $order->order_number ?? 'Order #'.$order->id }}</h1>
        <p>Status: {{ ucfirst($order->status) }} · Your items only</p>
    </div>
    <a class="btn btn-ghost" href="{{ route('vendor.orders.index') }}">Back to orders</a>
</div>

<div class="panel section">
    <p style="color:var(--color-ink-muted);margin-top:0;">Customer: {{ $order->user->name ?? 'Customer' }}</p>
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th align="left">Product</th>
                <th align="left">Qty</th>
                <th align="left">Unit price</th>
                <th align="left">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr style="border-top:1px solid var(--color-border);">
                    <td style="padding:.75rem 0;">{{ $item->product->name ?? 'Product #'.$item->product_id }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>TSh {{ number_format($item->price, 0) }}</td>
                    <td>TSh {{ number_format((float) $item->price * $item->quantity, 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="margin-top:1.25rem;"><strong>Your subtotal:</strong> TSh {{ number_format((float) $vendorSubtotal, 0) }}</p>
    <p style="color:var(--color-ink-muted);font-size:.9rem;">Full order totals and other sellers’ items are not shown.</p>
</div>
@endsection
