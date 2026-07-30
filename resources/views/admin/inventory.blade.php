@extends('layouts.app')
@section('title', 'Inventory')
@section('content')
@include('admin._nav')
<h1 class="font-display">Inventory</h1>
<div class="panel" style="overflow:auto;margin-top:1rem;">
<table style="width:100%;border-collapse:collapse;min-width:640px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);"><th style="padding:.75rem;">Product</th><th>SKU</th><th>Stock</th><th>Sold</th></tr></thead>
<tbody>
@foreach($products as $product)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $product->name }}</td>
<td>{{ $product->sku }}</td>
<td><span class="badge {{ $product->stock < 10 ? 'badge-sale' : 'badge-stock' }}">{{ $product->stock }}</span></td>
<td>{{ $product->sold_count }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $products->links() }}</div>
@endsection
