@extends('layouts.app')
@section('title', 'Payment reconciliations')
@section('content')
@include('admin._nav')
<h1 class="font-display">Payment reconciliations</h1>
<p style="color:var(--color-ink-muted);">Mismatches between local payment state and provider verification. Records are not auto-corrected.</p>
<div class="panel" style="overflow:auto;margin-top:1rem;">
<table style="width:100%;border-collapse:collapse;min-width:800px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">ID</th><th>Order</th><th>Local</th><th>Provider</th><th>Severity</th><th>Status</th><th>Detail</th>
</tr></thead>
<tbody>
@forelse($rows as $row)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $row->id }}</td>
<td>{{ $row->order?->order_number ?? '—' }}</td>
<td>{{ $row->local_status }}</td>
<td>{{ $row->provider_status }}</td>
<td>{{ $row->severity }}</td>
<td>{{ $row->status }}</td>
<td>{{ $row->detail }}</td>
</tr>
@empty
<tr><td colspan="7" style="padding:1rem;color:var(--color-ink-muted);">No reconciliation items.</td></tr>
@endforelse
</tbody>
</table>
</div>
{{ $rows->links() }}
@endsection
