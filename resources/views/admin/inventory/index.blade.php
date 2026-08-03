@extends('layouts.app')
@section('title', 'Inventory')
@section('content')
@include('admin._nav')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Inventory</h1>
        <p>Available stock with product-level reorder thresholds</p>
    </div>
    @if(auth()->user()->hasPermission('inventory.history'))
        <a class="btn btn-ghost" href="{{ route('admin.inventory.history') }}">History</a>
    @endif
</div>

<form method="GET" class="panel" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;">
    <input class="form-control" style="max-width:240px;" type="search" name="q" value="{{ request('q') }}" placeholder="Search product/SKU">
    <select class="form-control" style="max-width:160px;" name="stock">
        <option value="">All stock</option>
        <option value="low" @selected(request('stock')==='low')>Low stock</option>
        <option value="out" @selected(request('stock')==='out')>Out of stock</option>
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>

@if($errors->any())
<div class="panel" style="border-color:#b91c1c;margin-bottom:1rem;">{{ $errors->first() }}</div>
@endif
@if(session('success'))
<div class="panel" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

<div class="panel" style="overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:880px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">Product</th><th>SKU</th><th>Vendor</th><th>Available</th><th>Reserved</th><th>Reorder</th><th>Status</th><th>Adjust</th>
</tr></thead>
<tbody>
@foreach($products as $product)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $product->name }}</td>
<td>{{ $product->sku ?: '—' }}</td>
<td>{{ $product->vendor->store_name ?? '—' }}</td>
<td>
    @if($product->isOutOfStock())
        <span class="badge badge-sale">OUT {{ $product->stock }}</span>
    @elseif($product->isLowStock())
        <span class="badge badge-sale">LOW {{ $product->stock }}</span>
    @else
        {{ $product->stock }}
    @endif
</td>
<td>{{ $product->reserved_quantity ?? 0 }}</td>
<td>{{ $product->reorder_level ?? 5 }}</td>
<td>{{ $product->isOutOfStock() ? 'OUT OF STOCK' : ($product->isLowStock() ? 'LOW STOCK' : 'OK') }}</td>
<td style="padding:.5rem;">
@can('adjustInventory', $product)
<form method="POST" action="{{ route('admin.inventory.adjust', $product) }}" style="display:flex;gap:.35rem;align-items:center;">
    @csrf
    <input class="form-control" style="width:88px;" type="number" name="delta" required placeholder="+/-">
    <input class="form-control" style="width:160px;" type="text" name="reason" required placeholder="Reason">
    <select class="form-control" style="width:120px;" name="type">
        <option value="adjustment">Adjust</option>
        <option value="damage">Damage</option>
        <option value="return">Return</option>
    </select>
    <button class="btn btn-primary" type="submit" style="padding:.35rem .65rem;">Apply</button>
</form>
@else
—
@endcan
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $products->links() }}</div>
@endsection
