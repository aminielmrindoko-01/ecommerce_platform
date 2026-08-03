@extends('layouts.app')
@section('title', 'Refunds')
@section('content')
@include('admin._nav')
<h1 class="font-display">Refunds</h1>
<div class="panel" style="overflow:auto;margin-top:1rem;">
<table style="width:100%;border-collapse:collapse;min-width:800px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">Reference</th><th>Order</th><th>Amount</th><th>Status</th><th>Actor</th><th>Completed</th>
</tr></thead>
<tbody>
@forelse($refunds as $refund)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $refund->reference }}</td>
<td>{{ $refund->order?->order_number ?? '#'.$refund->order_id }}</td>
<td>{{ money($refund->amount) }} {{ $refund->currency }}</td>
<td>{{ ucfirst($refund->status) }}</td>
<td>{{ $refund->actor?->name ?? '—' }}</td>
<td>{{ $refund->completed_at?->format('M d, Y H:i') ?? '—' }}</td>
</tr>
@empty
<tr><td colspan="6" style="padding:1rem;color:var(--color-ink-muted);">No refunds yet.</td></tr>
@endforelse
</tbody>
</table>
</div>
{{ $refunds->links() }}
@endsection
