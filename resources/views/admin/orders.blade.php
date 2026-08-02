@extends('layouts.app')
@section('title', 'Admin orders')
@section('content')
@include('admin._nav')
<h1 class="font-display">Orders</h1>
<p style="color:var(--color-ink-muted);">Order status is the legacy admin lifecycle. Payment status is controlled separately. Item fulfillment is per vendor line.</p>

@foreach($orders as $order)
@php
    $payment = $order->latestPaymentTransaction;
    $paymentNext = $order->admin_allowed_payments ?? [];
@endphp
<div class="panel" style="margin-top:1rem;">
    <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:flex-start;">
        <div>
            <strong>{{ $order->order_number ?? '#'.$order->id }}</strong>
            <div style="color:var(--color-ink-muted);font-size:.9rem;">
                {{ $order->user->name ?? '—' }} · TSh {{ number_format($order->total_price,0) }} · {{ $order->created_at?->format('M d, Y') }}
                · Fulfillment: {{ $order->fulfillment_summary_label ?? '—' }}
            </div>
            <div style="margin-top:.55rem;font-size:.9rem;line-height:1.6;">
                <div><strong>Payment method:</strong> {{ strtoupper(str_replace('_', ' ', $order->payment_method ?? 'n/a')) }}</div>
                <div><strong>Payment status:</strong> {{ ucfirst($order->payment_status ?? 'pending') }}</div>
                @if($payment)
                    <div><strong>Reference:</strong> {{ $payment->reference }}</div>
                    <div><strong>Amount:</strong> TSh {{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</div>
                    <div><strong>Txn status:</strong> {{ ucfirst($payment->status) }}</div>
                    @if($payment->paid_at)
                        <div><strong>Paid at:</strong> {{ $payment->paid_at->format('M d, Y H:i') }}</div>
                    @endif
                @endif
            </div>
        </div>
        <div style="display:grid;gap:.75rem;justify-items:end;">
            <form method="POST" action="{{ route('admin.orders.update', $order->id) }}" style="display:flex;gap:.4rem;align-items:center;">
                @csrf @method('PUT')
                <label class="sr-only" for="order-status-{{ $order->id }}">Order status</label>
                <select id="order-status-{{ $order->id }}" name="status" class="form-control" style="width:auto;">
                    @foreach(['pending','paid','shipped','completed'] as $status)
                        <option value="{{ $status }}" @selected($order->status === $status)>Order: {{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-ghost" type="submit" style="padding:.45rem .7rem;">Save order status</button>
            </form>

            @if(count($paymentNext))
                <form method="POST" action="{{ route('admin.orders.payment', $order) }}" style="display:grid;gap:.35rem;min-width:260px;">
                    @csrf
                    @method('PATCH')
                    <select name="payment_status" class="form-control" required>
                        <option value="" disabled selected>Update payment to…</option>
                        @foreach($paymentNext as $status)
                            <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="provider_reference" class="form-control" maxlength="128" placeholder="Provider reference (optional)">
                    <input type="text" name="reason" class="form-control" maxlength="500" placeholder="Reason (required for failed/cancelled)">
                    <button class="btn btn-primary" type="submit" style="padding:.4rem .65rem;">Update payment</button>
                </form>
            @else
                <span style="color:var(--color-ink-muted);font-size:.9rem;">No payment actions available</span>
            @endif
        </div>
    </div>

    <table style="width:100%;border-collapse:collapse;margin-top:1rem;min-width:720px;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid var(--color-border);">
                <th style="padding:.5rem 0;">Vendor</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Item fulfillment</th>
                <th>Admin override</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->items as $item)
                @php $next = $order->admin_allowed_by_item[$item->id] ?? []; @endphp
                <tr style="border-bottom:1px solid var(--color-border);vertical-align:top;">
                    <td style="padding:.55rem 0;">{{ $item->product->vendor->store_name ?? '—' }}</td>
                    <td>{{ $item->product->name ?? 'Product #'.$item->product_id }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>TSh {{ number_format($item->price, 0) }}</td>
                    <td><span class="chip">{{ ucfirst($item->fulfillment_status ?? 'pending') }}</span></td>
                    <td style="padding:.55rem 0;">
                        @if(count($next))
                            <form method="POST" action="{{ route('admin.orders.items.fulfillment', [$order, $item]) }}" style="display:grid;gap:.35rem;max-width:280px;">
                                @csrf
                                @method('PATCH')
                                <select name="fulfillment_status" class="form-control" required>
                                    <option value="" disabled selected>Set to…</option>
                                    @foreach($next as $status)
                                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="reason" class="form-control" maxlength="500" placeholder="Reason (required for some overrides)">
                                <button class="btn btn-primary" type="submit" style="padding:.4rem .65rem;">Override</button>
                            </form>
                        @else
                            <span style="color:var(--color-ink-muted);font-size:.9rem;">Terminal status</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:.75rem 0;color:var(--color-ink-muted);">No items</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endforeach

<div style="margin-top:1rem;">{{ $orders->links() }}</div>
@endsection
