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

@foreach($orders as $order)
@php
    $payment = $order->latestPaymentTransaction;
    $paymentNext = $order->admin_allowed_payments ?? [];
    $paymentInit = $order->admin_payment_init ?? [];
@endphp
<div class="panel" style="margin-top:1rem;">
    <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:flex-start;">
        <div style="flex:1;min-width:280px;">
            <strong>{{ $order->order_number ?? '#'.$order->id }}</strong>
            <div style="color:var(--color-ink-muted);font-size:.9rem;">
                {{ $order->user->name ?? '—' }} · {{ money($order->total_price) }} · {{ $order->created_at?->format('M d, Y') }}
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.65rem;margin-top:.85rem;">
                <div class="panel" style="margin:0;padding:.75rem;background:var(--color-surface);">
                    <div style="font-size:.72rem;text-transform:uppercase;color:var(--color-ink-muted);letter-spacing:.04em;">Order status</div>
                    <strong>{{ ucfirst($order->status ?? 'pending') }}</strong>
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
            <form method="POST" action="{{ route('admin.orders.update', $order->id) }}" style="display:grid;gap:.35rem;justify-items:end;width:100%;">
                @csrf @method('PUT')
                <label class="sr-only" for="order-status-{{ $order->id }}">Order status</label>
                @if(($order->status ?? '') === 'paid')
                    <div style="font-size:.9rem;color:var(--color-ink-muted);">Order status: <strong>Paid</strong> (via payment)</div>
                    <select id="order-status-{{ $order->id }}" name="status" class="form-control" style="width:100%;">
                        <option value="shipped">Move to Shipped</option>
                        <option value="completed">Move to Completed</option>
                    </select>
                @else
                    <select id="order-status-{{ $order->id }}" name="status" class="form-control" style="width:100%;">
                        @foreach(['pending','shipped','completed'] as $status)
                            <option value="{{ $status }}" @selected($order->status === $status)>Order: {{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="btn btn-ghost" type="submit" style="padding:.45rem .7rem;">Save order status</button>
                <p style="margin:0;font-size:.8rem;color:var(--color-ink-muted);max-width:280px;text-align:right;">
                    Does not change payment status. Paid is only via Update payment → PaymentService.
                </p>
            </form>

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
                    <td style="padding:.65rem 0;">{{ $item->product->vendor->store_name ?? '—' }}</td>
                    <td>{{ $item->product->name ?? 'Product' }}</td>
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
