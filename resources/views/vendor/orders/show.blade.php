@extends('layouts.app')
@section('title', 'Order '.$order->order_number)
@section('content')
@include('vendor._nav')

<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">{{ $order->order_number ?? 'Order #'.$order->id }}</h1>
        <p>Order status: {{ ucfirst($order->status) }} · {{ $order->created_at?->format('M d, Y H:i') }} · Your items only</p>
    </div>
    <a class="btn btn-ghost" href="{{ route('vendor.orders.index') }}">Back to orders</a>
</div>

<div class="panel section">
    <h2 style="margin-top:0;font-size:1.05rem;">Ship to</h2>
    <p style="margin:.4rem 0 0;line-height:1.7;color:var(--color-ink-muted);">
        {{ $shipping['full_name'] ?? 'Customer' }}<br>
        @if(! empty($shipping['phone']))
            {{ $shipping['phone'] }}<br>
        @endif
        @if(! empty($shipping['line1']))
            {{ $shipping['line1'] }} {{ $shipping['line2'] ?? '' }}<br>
        @endif
        @if(! empty($shipping['city']))
            {{ $shipping['city'] }}@if(! empty($shipping['region'])), {{ $shipping['region'] }}@endif
        @endif
    </p>
</div>

<div class="panel section">
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th align="left">Product</th>
                <th align="left">Qty</th>
                <th align="left">Unit price</th>
                <th align="left">Line total</th>
                <th align="left">Fulfillment</th>
                <th align="left">Next action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr style="border-top:1px solid var(--color-border);">
                    <td style="padding:.75rem 0;">{{ $item->product->name ?? 'Product #'.$item->product_id }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>TSh {{ number_format($item->price, 0) }}</td>
                    <td>TSh {{ number_format((float) $item->lineTotal(), 0) }}</td>
                    <td><span class="chip">{{ ucfirst($item->fulfillment_status ?? 'pending') }}</span></td>
                    <td style="padding:.75rem 0;">
                        @php $next = $allowedByItem[$item->id] ?? []; @endphp
                        @if(count($next))
                            <form method="POST" action="{{ route('vendor.orders.items.fulfillment', [$order, $item]) }}" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;">
                                @csrf
                                @method('PATCH')
                                <select name="fulfillment_status" class="form-control" style="width:auto;" required>
                                    <option value="" disabled selected>Update to…</option>
                                    @foreach($next as $status)
                                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-primary" type="submit" style="padding:.45rem .7rem;">Save</button>
                            </form>
                        @else
                            <span style="color:var(--color-ink-muted);font-size:.9rem;">No further actions</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="margin-top:1.25rem;"><strong>Your subtotal:</strong> TSh {{ number_format((float) $vendorSubtotal, 0) }}</p>
    <p style="color:var(--color-ink-muted);font-size:.9rem;">Full order totals and other sellers’ items are not shown. Order status ({{ ucfirst($order->status) }}) is separate from item fulfillment.</p>
</div>
@endsection
