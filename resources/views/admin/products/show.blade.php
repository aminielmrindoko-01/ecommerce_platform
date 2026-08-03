@extends('layouts.app')
@section('title', $product->name)
@section('content')
@include('admin._nav')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">{{ $product->name }}</h1>
        <p>{{ str_replace('_',' ', $product->status) }} · {{ $product->vendor->store_name ?? '—' }}</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        @can('update', $product)
            <a class="btn btn-primary" href="{{ route('admin.products.edit', $product) }}">Edit</a>
        @endcan
        <a class="btn btn-ghost" href="{{ route('admin.products.index') }}">Back</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.2fr 1fr;gap:1.25rem;">
    <div class="panel">
        <img src="{{ $product->image_url }}" alt="" style="width:100%;max-height:360px;object-fit:cover;border-radius:12px;">
        <p style="margin-top:1rem;">{{ $product->description ?: 'No description.' }}</p>
    </div>
    <div class="panel" style="display:grid;gap:.65rem;">
        <div><strong>SKU</strong><div>{{ $product->sku ?: '—' }}</div></div>
        <div><strong>Price</strong><div>TSh {{ number_format($product->price, 0) }}</div></div>
        <div><strong>Available</strong><div>{{ $product->stock }}</div></div>
        <div><strong>Reserved</strong><div>{{ $product->reserved_quantity ?? 0 }}</div></div>
        <div><strong>Reorder level</strong><div>{{ $product->reorder_level ?? 5 }}</div></div>
        <div><strong>Category</strong><div>{{ $product->category->name ?? '—' }}</div></div>
        <div><strong>Published</strong><div>{{ $product->published_at?->toDayDateTimeString() ?: '—' }}</div></div>
        <div><strong>Created</strong><div>{{ $product->created_at?->toDayDateTimeString() }}</div></div>
    </div>
</div>

@if($product->inventoryMovements->isNotEmpty())
<div class="panel" style="margin-top:1.25rem;overflow:auto;">
    <h2 style="margin-top:0;font-size:1.1rem;">Recent inventory</h2>
    <table style="width:100%;border-collapse:collapse;">
        <thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
            <th style="padding:.5rem;">Date</th><th>Before</th><th>Change</th><th>After</th><th>Reason</th>
        </tr></thead>
        <tbody>
        @foreach($product->inventoryMovements as $m)
            <tr style="border-bottom:1px solid var(--color-border);">
                <td style="padding:.5rem;">{{ $m->created_at }}</td>
                <td>{{ $m->quantity_before }}</td>
                <td>{{ $m->quantity_delta > 0 ? '+'.$m->quantity_delta : $m->quantity_delta }}</td>
                <td>{{ $m->quantity_after }}</td>
                <td>{{ $m->reason }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
