@extends('layouts.app')
@section('title', 'Admin orders')
@section('content')
@include('admin._nav')
@php use App\Support\Payments\PaymentStatusPresenter; @endphp
<h1 class="font-display">Orders</h1>
<p style="color:var(--color-ink-muted);">
    Keep these separate:
    <strong>Order status</strong> (lifecycle) ·
    <strong>Payment status</strong> (PaymentService only) ·
    <strong>Fulfillment status</strong> (per line item).
    Active gateway: <strong>{{ $activePaymentGateway ?? 'Stub / Offline / Coming Soon' }}</strong> — live charging disabled.
</p>

<form method="GET" action="{{ route('admin.orders') }}" class="panel" style="display:flex;gap:.75rem;flex-wrap:wrap;margin:1rem 0;">
    <input class="form-control" style="max-width:220px;" type="search" name="q" value="{{ request('q') }}" placeholder="Order # or customer">
    <select class="form-control" style="max-width:180px;" name="status">
        <option value="">All order statuses</option>
        @foreach (['pending','confirmed','processing','ready_for_fulfillment','shipped','delivered','cancelled','refunded','paid','completed'] as $st)
            <option value="{{ $st }}" @selected(request('status') === $st)>{{ str_replace('_', ' ', ucfirst($st)) }}</option>
        @endforeach
    </select>
    <select class="form-control" style="max-width:160px;" name="payment_status">
        <option value="">All payments</option>
        @foreach (['pending','processing','paid','failed','cancelled','refunded'] as $ps)
            <option value="{{ $ps }}" @selected(request('payment_status') === $ps)>{{ ucfirst($ps) }}</option>
        @endforeach
    </select>
    <select class="form-control" style="max-width:140px;" name="sort">
        <option value="newest" @selected(request('sort','newest') === 'newest')>Newest</option>
        <option value="oldest" @selected(request('sort') === 'oldest')>Oldest</option>
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>

@if(session('error'))
    <div class="panel" style="border-color:#b91c1c;margin-bottom:1rem;">{{ session('error') }}</div>
@endif
@if(session('success'))
    <div class="panel" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

@foreach($orders as $order)
@php
    $payment = $order->latestPaymentTransaction;
    $paymentNext = $order->admin_allowed_payments ?? [];
    $paymentInit = $order->admin_payment_init ?? [];
    $nextStatuses = $order->admin_allowed_next_statuses ?? [];
    $vendorsSummary = $order->items
        ->map(fn ($i) => $i->vendor_store_name ?: $i->product?->vendor?->store_name)
        ->filter()
        ->unique()
        ->implode(', ');
@endphp
<div class="panel" style="margin-top:1rem;">
    <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:flex-start;">
        <div style="flex:1;min-width:280px;">
            <strong>{{ $order->order_number ?? '#'.$order->id }}</strong>
            <div style="color:var(--color-ink-muted);font-size:.9rem;">
                {{ $order->user->name ?? '—' }} · {{ money($order->total_price) }}
                · {{ strtoupper($order->currency ?? 'TZS') }}
                · {{ $order->created_at?->format('M d, Y H:i') }}
            </div>
            <div style="color:var(--color-ink-muted);font-size:.85rem;margin-top:.35rem;">
                {{ $order->items->count() }} item(s)
                @if($vendorsSummary) · Vendors: {{ $vendorsSummary }} @endif
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.65rem;margin-top:.85rem;">
                <div class="panel" style="margin:0;padding:.75rem;background:var(--color-surface);">
                    <div style="font-size:.72rem;text-transform:uppercase;color:var(--color-ink-muted);letter-spacing:.04em;">Order status</div>
                    <strong>{{ str_replace('_', ' ', ucfirst($order->status ?? 'pending')) }}</strong>
                </div>
                <div class="panel" style="margin:0;padding:.75rem;background:var(--color-surface);">
                    <div style="font-size:.72rem;text-transform:uppercase;color:var(--color-ink-muted);letter-spacing:.04em;">Payment status</div>
                    <strong>{{ PaymentStatusPresenter::label($order->payment_status ?? 'pending') }}</strong>
                </div>
                <div class="panel" style="margin:0;padding:.75rem;background:var(--color-surface);">
                    <div style="font-size:.72rem;text-transform:uppercase;color:var(--color-ink-muted);letter-spacing:.04em;">Fulfillment</div>
                    <strong>Fulfillment: {{ $order->fulfillment_summary_label ?? '—' }}</strong>
                </div>
            </div>

            @include('partials.payment-status-panel', [
                'order' => $order,
                'payment' => $payment,
                'paymentInit' => $paymentInit,
                'context' => 'admin',
            ])
        </div>

        <div style="display:grid;gap:.75rem;justify-items:end;min-width:260px;">
            @canPermission('orders.update')
                @if(count($nextStatuses))
                    <form method="POST" action="{{ route('admin.orders.update', $order->id) }}" style="display:grid;gap:.35rem;justify-items:end;width:100%;">
                        @csrf @method('PUT')
                        <label class="sr-only" for="order-status-{{ $order->id }}">Order status</label>
                        <select id="order-status-{{ $order->id }}" name="status" class="form-control" style="width:100%;" required>
                            <option value="" disabled selected>Next status…</option>
                            @foreach($nextStatuses as $status)
                                @continue($status === 'paid')
                                <option value="{{ $status }}">{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="reason" class="form-control" maxlength="500" placeholder="Reason (optional)" autocomplete="off">
                        <button class="btn btn-ghost" type="submit" style="padding:.45rem .7rem;">Save order status</button>
                        <p style="margin:0;font-size:.8rem;color:var(--color-ink-muted);max-width:280px;text-align:right;">
                            Controlled transitions only. Does not change payment status.
                        </p>
                    </form>
                @else
                    <span style="color:var(--color-ink-muted);font-size:.9rem;">No further order transitions</span>
                @endif
            @endcanPermission

            @if(count($paymentNext))
                <form method="POST" action="{{ route('admin.orders.payment', $order) }}" style="display:grid;gap:.35rem;width:100%;">
                    @csrf
                    @method('PATCH')
                    <label style="font-size:.8rem;color:var(--color-ink-muted);" for="payment-status-{{ $order->id }}">Update payment status</label>
                    <select id="payment-status-{{ $order->id }}" name="payment_status" class="form-control" required>
                        <option value="" disabled selected>Update payment to…</option>
                        @foreach($paymentNext as $status)
                            <option value="{{ $status }}">{{ PaymentStatusPresenter::label($status) }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="provider_reference" class="form-control" maxlength="128" placeholder="Provider reference (optional)" autocomplete="off">
                    <input type="text" name="reason" class="form-control" maxlength="500" placeholder="Reason (required for failed/cancelled)" autocomplete="off">
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
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td style="padding:.65rem 0;">{{ $item->vendor_store_name ?? $item->product->vendor->store_name ?? '—' }}</td>
                    <td>{{ $item->displayName() }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ money($item->price) }}</td>
                    <td><span class="chip">{{ ucfirst($item->fulfillment_status ?? 'pending') }}</span></td>
                    <td>
                        @if(count($next))
                            <form method="POST" action="{{ route('admin.orders.items.fulfillment', [$order, $item]) }}" style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;">
                                @csrf
                                @method('PATCH')
                                <select name="fulfillment_status" class="form-control" required>
                                    <option value="" disabled selected>Set…</option>
                                    @foreach($next as $status)
                                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="reason" class="form-control" placeholder="Reason if required" maxlength="500">
                                <button class="btn btn-ghost" type="submit" style="padding:.35rem .55rem;">Save</button>
                            </form>
                        @else
                            <span style="color:var(--color-ink-muted);font-size:.9rem;">Terminal status</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:1rem 0;color:var(--color-ink-muted);">No items</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endforeach

{{ $orders->links() }}
@endsection
