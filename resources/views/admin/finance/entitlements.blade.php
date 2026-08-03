@extends('layouts.app')
@section('title', 'Entitlements')
@section('content')
@include('admin._nav')
@include('admin.finance._nav')
<h1 class="font-display">Vendor entitlements</h1>
<div class="panel" style="overflow:auto;margin-top:1rem;">
<table style="width:100%;border-collapse:collapse;min-width:900px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">Order</th><th>Vendor</th><th>Gross</th><th>Commission</th><th>Net</th><th>Status</th>
</tr></thead>
<tbody>
@foreach($ents as $ent)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $ent->order?->order_number }}</td>
<td>{{ $ent->vendor?->store_name }}</td>
<td>{{ money($ent->gross_amount) }}</td>
<td>{{ money($ent->commission_amount) }} ({{ $ent->commission_rate }})</td>
<td>{{ money($ent->net_amount) }}</td>
<td>{{ $ent->status }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
{{ $ents->links() }}
@endsection
