@extends('layouts.app')
@section('title', 'Admin products')
@section('content')
@include('admin._nav')
<div class="section-head">
    <div><h1 class="font-display" style="margin:0;">Products</h1><p>Catalog management</p></div>
    <a class="btn btn-primary" href="{{ route('products.create') }}">Add product</a>
</div>
<div class="panel" style="overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:720px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">Product</th><th>Vendor</th><th>Stock</th><th>Price</th><th>Actions</th>
</tr></thead>
<tbody>
@foreach($products as $product)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;display:flex;gap:.65rem;align-items:center;">
<img src="{{ $product->image_url }}" alt="" width="48" height="48" style="border-radius:8px;object-fit:cover;">
<span>{{ $product->name }}</span>
</td>
<td>{{ $product->vendor->store_name ?? '—' }}</td>
<td>{{ $product->stock }}</td>
<td>TSh {{ number_format($product->price,0) }}</td>
<td style="padding:.75rem;">
<a class="btn btn-ghost" href="{{ route('products.edit', $product->id) }}" style="padding:.35rem .65rem;">Edit</a>
<form action="{{ route('admin.products.destroy', $product->id) }}" method="POST" style="display:inline;">@csrf @method('DELETE')
<button class="btn btn-danger" type="submit" style="padding:.35rem .65rem;" onclick="return confirm('Delete product?')">Delete</button>
</form>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $products->links() }}</div>
@endsection
