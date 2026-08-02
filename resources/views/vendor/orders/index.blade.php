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

<div class="panel section" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;">
    <span style="color:var(--color-ink-muted);margin-right:.35rem;">Filter fulfillment:</span>
    <a class="btn {{ empty($fulfillmentFilter) ? 'btn-primary' : 'btn-ghost' }}" style="padding:.35rem .7rem;" href="{{ route('vendor.orders.index') }}">All</a>
    @foreach($allowedFilters as $status)
        <a class="btn {{ ($fulfillmentFilter ?? null) === $status ? 'btn-primary' : 'btn-ghost' }}" style="padding:.35rem .7rem;" href="{{ route('vendor.orders.index', ['fulfillment' => $status]) }}">{{ ucfirst($status) }}</a>
    @endforeach
</div>

<div class="panel section">
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th align="left">Order</th>
                <th align="left">Date</th>
                <th align="left">Customer</th>
                <th align="left">Order status</th>
                <th align="left">Your items</th>
                <th align="left">Fulfillment</th>
                <th align="left">Your subtotal</th>
                <th align="right"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                @php
                    $statuses = $order->items->pluck('fulfillment_status')->unique()->values();
                @endphp
                <tr style="border-top:1px solid var(--color-border);">
                    <td style="padding:.75rem 0;"><strong>{{ $order->order_number ?? '#'.$order->id }}</strong></td>
                    <td>{{ $order->created_at?->format('M d, Y') }}</td>
                    <td>{{ $order->user->name ?? 'Customer' }}</td>
                    <td>{{ ucfirst($order->status) }}</td>
                    <td>{{ $order->items->count() }}</td>
                    <td>
                        @foreach($statuses as $status)
                            <span class="chip" style="margin:0 .15rem .15rem 0;">{{ ucfirst($status ?? 'pending') }}</span>
                        @endforeach
                    </td>
                    <td>TSh {{ number_format((float) $order->vendor_subtotal, 0) }}</td>
                    <td align="right"><a class="btn btn-ghost" href="{{ route('vendor.orders.show', $order) }}" style="padding:.35rem .75rem;">Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="8" style="padding:1rem 0;color:var(--color-ink-muted);">No orders yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem;">{{ $orders->links() }}</div>
</div>
@endsection
