@extends('layouts.app')
@section('title', 'Inventory history')
@section('content')
@include('admin._nav')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Inventory history</h1>
        <p>Append-only stock movement log</p>
    </div>
    <a class="btn btn-ghost" href="{{ route('admin.inventory.index') }}">Back</a>
</div>

<div class="panel" style="overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:900px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">Date</th><th>Product</th><th>Before</th><th>Change</th><th>After</th><th>Reason</th><th>Actor</th>
</tr></thead>
<tbody>
@forelse($movements as $m)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $m->created_at }}</td>
<td>{{ $m->product->name ?? '—' }}</td>
<td>{{ $m->quantity_before }}</td>
<td>{{ $m->quantity_delta > 0 ? '+'.$m->quantity_delta : $m->quantity_delta }}</td>
<td>{{ $m->quantity_after }}</td>
<td>{{ $m->reason }}</td>
<td>{{ $m->actor->name ?? 'System' }}</td>
</tr>
@empty
<tr><td colspan="7" style="padding:1rem;">No movements yet.</td></tr>
@endforelse
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $movements->links() }}</div>
@endsection
