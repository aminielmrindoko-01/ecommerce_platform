@extends('layouts.app')
@section('title', 'Vendors')
@section('content')
@include('admin._nav')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Vendors</h1>
        <p>Marketplace seller lifecycle and performance</p>
    </div>
</div>

<form method="GET" class="panel" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;">
    <input class="form-control" style="max-width:220px;" type="search" name="q" value="{{ request('q') }}" placeholder="Store or email">
    <select class="form-control" style="max-width:180px;" name="status">
        <option value="">All statuses</option>
        @foreach(['pending','under_review','approved','suspended','rejected','inactive'] as $st)
            <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst(str_replace('_',' ',$st)) }}</option>
        @endforeach
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>

@if(session('error'))<div class="panel" style="border-color:#b91c1c;margin-bottom:1rem;">{{ session('error') }}</div>@endif
@if(session('success'))<div class="panel" style="margin-bottom:1rem;">{{ session('success') }}</div>@endif

<div class="panel" style="overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:960px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">Store</th><th>Status</th><th>Products</th><th>Published</th><th>Sales (items)</th><th>Owner</th><th>Actions</th>
</tr></thead>
<tbody>
@foreach($vendors as $vendor)
@php $sales = $salesByVendor[$vendor->id] ?? null; @endphp
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">
    <strong>{{ $vendor->store_name }}</strong>
    <div style="color:var(--color-ink-muted);font-size:.85rem;">{{ $vendor->email }}</div>
</td>
<td><span class="badge">{{ str_replace('_',' ', $vendor->status ?? ($vendor->is_verified ? 'approved' : 'pending')) }}</span></td>
<td>{{ $vendor->products_count }}</td>
<td>{{ $vendor->published_products_count }}</td>
<td>
    @if($sales)
        {{ number_format((float) $sales->sales_value, 0) }} TZS
        <div style="color:var(--color-ink-muted);font-size:.8rem;">{{ $sales->item_rows }} lines</div>
    @else
        —
    @endif
</td>
<td>{{ $vendor->user->name ?? '—' }}</td>
<td style="padding:.5rem;">
<form method="POST" action="{{ route('admin.vendors.status', $vendor) }}" style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;">
    @csrf
    <select class="form-control" name="status" style="width:140px;">
        @foreach(['pending','under_review','approved','suspended','rejected','inactive'] as $st)
            <option value="{{ $st }}" @selected(($vendor->status ?? '') === $st)>{{ $st }}</option>
        @endforeach
    </select>
    <input class="form-control" style="width:140px;" type="text" name="notes" placeholder="Notes">
    <button class="btn btn-primary" type="submit" style="padding:.35rem .65rem;">Update</button>
</form>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $vendors->links() }}</div>
@endsection
