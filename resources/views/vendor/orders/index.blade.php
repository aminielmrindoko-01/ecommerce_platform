@extends('layouts.app')
@section('title', 'Vendor orders')
@section('content')
@include('vendor._nav')

<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Orders</h1>
        <p>Orders that include your products — only your line items are shown</p>
    </div>
</div>

<div class="panel section">
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th align="left">Order</th>
                <th align="left">Customer</th>
                <th align="left">Status</th>
                <th align="left">Your items</th>
                <th align="left">Your subtotal</th>
                <th align="right"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                <tr style="border-top:1px solid var(--color-border);">
                    <td style="padding:.75rem 0;"><strong>{{ $order->order_number ?? '#'.$order->id }}</strong></td>
                    <td>{{ $order->user->name ?? 'Customer' }}</td>
                    <td>{{ ucfirst($order->status) }}</td>
                    <td>{{ $order->items->count() }}</td>
                    <td>TSh {{ number_format((float) $order->vendor_subtotal, 0) }}</td>
                    <td align="right"><a class="btn btn-ghost" href="{{ route('vendor.orders.show', $order) }}" style="padding:.35rem .75rem;">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:1rem 0;color:var(--color-ink-muted);">No orders yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem;">{{ $orders->links() }}</div>
</div>
@endsection
