@extends('layouts.app')
@section('title', 'Admin products')
@section('content')
@include('admin._nav')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Products</h1>
        <p>Catalog management with lifecycle and ownership controls</p>
    </div>
    @can('create', App\Models\Product::class)
        <a class="btn btn-primary" href="{{ route('admin.products.create') }}">Add product</a>
    @endcan
</div>

<form method="GET" class="panel" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;gap:.75rem;align-items:end;margin-bottom:1rem;">
    <div>
        <label class="form-label">Search</label>
        <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Name, SKU, brand">
    </div>
    <div>
        <label class="form-label">Status</label>
        <select class="form-control" name="status">
            <option value="">All</option>
            @foreach(['draft','pending_review','published','unpublished','suspended','archived'] as $st)
                <option value="{{ $st }}" @selected(request('status')===$st)>{{ str_replace('_',' ', ucfirst($st)) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Category</label>
        <select class="form-control" name="category_id">
            <option value="">All</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(request('category_id')==$cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Vendor</label>
        <select class="form-control" name="vendor_id">
            <option value="">All</option>
            @foreach($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected(request('vendor_id')==$vendor->id)>{{ $vendor->store_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Stock</label>
        <select class="form-control" name="stock">
            <option value="">All</option>
            <option value="low" @selected(request('stock')==='low')>Low</option>
            <option value="out" @selected(request('stock')==='out')>Out</option>
        </select>
    </div>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>

<div class="panel" style="overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:960px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">Product</th><th>SKU</th><th>Vendor</th><th>Category</th><th>Status</th><th>Stock</th><th>Price</th><th>Created</th><th>Actions</th>
</tr></thead>
<tbody>
@forelse($products as $product)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;display:flex;gap:.65rem;align-items:center;">
<img src="{{ $product->image_url }}" alt="" width="48" height="48" style="border-radius:8px;object-fit:cover;">
<a href="{{ route('admin.products.show', $product) }}">{{ $product->name }}</a>
</td>
<td>{{ $product->sku ?: '—' }}</td>
<td>{{ $product->vendor->store_name ?? '—' }}</td>
<td>{{ $product->category->name ?? '—' }}</td>
<td><span class="badge">{{ str_replace('_',' ', $product->status) }}</span></td>
<td>
    @if($product->isOutOfStock())
        <span class="badge badge-sale">OUT</span>
    @elseif($product->isLowStock())
        <span class="badge badge-sale">LOW {{ $product->stock }}</span>
    @else
        {{ $product->stock }}
    @endif
</td>
<td>TSh {{ number_format($product->price,0) }}</td>
<td>{{ $product->created_at?->format('Y-m-d') }}</td>
<td style="padding:.75rem;display:flex;gap:.35rem;flex-wrap:wrap;">
<a class="btn btn-ghost" href="{{ route('admin.products.show', $product) }}" style="padding:.35rem .65rem;">View</a>
@can('update', $product)
<a class="btn btn-ghost" href="{{ route('admin.products.edit', $product) }}" style="padding:.35rem .65rem;">Edit</a>
@endcan
@can('publish', $product)
@if($product->status !== 'published')
<form action="{{ route('admin.products.publish', $product) }}" method="POST">@csrf
<button class="btn btn-primary" type="submit" style="padding:.35rem .65rem;">Publish</button>
</form>
@endif
@endcan
@can('unpublish', $product)
@if($product->status === 'published')
<form action="{{ route('admin.products.unpublish', $product) }}" method="POST">@csrf
<button class="btn btn-ghost" type="submit" style="padding:.35rem .65rem;">Unpublish</button>
</form>
@endif
@endcan
@can('delete', $product)
<form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Archive this product?')">@csrf @method('DELETE')
<button class="btn btn-danger" type="submit" style="padding:.35rem .65rem;">Archive</button>
</form>
@endcan
</td>
</tr>
@empty
<tr><td colspan="9" style="padding:1rem;">No products match your filters.</td></tr>
@endforelse
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $products->links() }}</div>
@endsection
