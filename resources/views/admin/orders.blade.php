@extends('layouts.app')
@section('title', 'Admin orders')
@section('content')
@include('admin._nav')
<h1 class="font-display">Orders</h1>
<div class="panel" style="margin-top:1rem;overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:700px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);"><th style="padding:.75rem;">Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Update</th></tr></thead>
<tbody>
@foreach($orders as $order)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $order->order_number ?? '#'.$order->id }}</td>
<td>{{ $order->user->name ?? '—' }}</td>
<td>TSh {{ number_format($order->total_price,0) }}</td>
<td>{{ ucfirst($order->status) }}</td>
<td style="padding:.75rem;">
<form method="POST" action="{{ route('admin.orders.update', $order->id) }}" style="display:flex;gap:.4rem;">
@csrf @method('PUT')
<select name="status" class="form-control" style="width:auto;">
@foreach(['pending','paid','shipped','completed'] as $status)
<option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>
@endforeach
</select>
<button class="btn btn-primary" type="submit" style="padding:.45rem .7rem;">Save</button>
</form>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $orders->links() }}</div>
@endsection
