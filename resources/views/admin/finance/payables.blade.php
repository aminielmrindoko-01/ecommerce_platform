@extends('layouts.app')
@section('title', 'Vendor payables')
@section('content')
@include('admin._nav')
@include('admin.finance._nav')
<h1 class="font-display">Vendor payables</h1>
<div class="panel" style="overflow:auto;margin-top:1rem;">
<table style="width:100%;border-collapse:collapse;min-width:800px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">Vendor</th><th>Sales</th><th>Commission</th><th>Ledger payable</th><th>Available</th><th>Paid out</th>
</tr></thead>
<tbody>
@foreach($vendors as $vendor)
@php $s = $summaries[$vendor->id]; @endphp
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $vendor->store_name }}</td>
<td>{{ money($s['sales_gross']) }}</td>
<td>{{ money($s['commission']) }}</td>
<td>{{ money($s['payable_ledger']) }}</td>
<td>{{ money($s['available']) }}</td>
<td>{{ money($s['paid_out']) }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
{{ $vendors->links() }}
@endsection
