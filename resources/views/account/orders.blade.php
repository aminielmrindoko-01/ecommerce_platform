@extends('layouts.app')
@section('title', 'Orders')
@section('content')
@include('account._nav')
<h1 class="font-display">Purchase history</h1>
<div class="panel" style="padding:0;margin-top:1rem;">
@forelse($orders as $order)
    <a href="{{ route('account.orders.show', $order) }}" style="display:flex;justify-content:space-between;gap:1rem;padding:1rem;border-bottom:1px solid var(--color-border);flex-wrap:wrap;">
        <div>
            <strong>{{ $order->order_number ?? '#'.$order->id }}</strong>
            <div style="color:var(--color-ink-muted);font-size:.9rem;">{{ $order->created_at->format('M d, Y') }} · {{ $order->items->count() }} item(s)</div>
        </div>
        <div style="text-align:right;">
            <strong>TSh {{ number_format($order->total_price, 0) }}</strong>
            <div style="color:var(--color-ink-muted);">{{ ucfirst($order->status) }}</div>
        </div>
    </a>
@empty
    <p style="padding:1.5rem;color:var(--color-ink-muted);">No orders yet.</p>
@endforelse
</div>
<div style="margin-top:1rem;">{{ $orders->links() }}</div>
@endsection
