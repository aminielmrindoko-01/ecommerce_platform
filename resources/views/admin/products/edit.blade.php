@extends('layouts.app')
@section('title', isset($product) ? 'Edit product' : 'Create product')
@section('content')
@include('admin._nav')
@php $editing = isset($product); @endphp
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">{{ $editing ? 'Edit product' : 'Create product' }}</h1>
        <p>Validated catalog fields with server-side ownership</p>
    </div>
    <a class="btn btn-ghost" href="{{ route('admin.products.index') }}">Back</a>
</div>

@if($errors->any())
<div class="panel" style="border-color:#b91c1c;margin-bottom:1rem;">
    <ul style="margin:0;padding-left:1.2rem;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" class="panel" style="display:grid;gap:1rem;max-width:820px;">
    @csrf
    @if($editing) @method('PUT') @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div>
            <label class="form-label">Name</label>
            <input class="form-control" name="name" value="{{ old('name', $product->name ?? '') }}" required>
        </div>
        <div>
            <label class="form-label">SKU</label>
            <input class="form-control" name="sku" value="{{ old('sku', $product->sku ?? '') }}" placeholder="Optional unique SKU">
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
        <div>
            <label class="form-label">Price (TZS)</label>
            <input class="form-control" type="number" min="0" step="1" name="price" value="{{ old('price', $product->price ?? '') }}" required>
        </div>
        <div>
            <label class="form-label">Compare-at</label>
            <input class="form-control" type="number" min="0" step="1" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price ?? '') }}">
        </div>
        <div>
            <label class="form-label">Brand</label>
            <input class="form-control" name="brand" value="{{ old('brand', $product->brand ?? '') }}">
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div>
            <label class="form-label">Vendor</label>
            <select class="form-control" name="vendor_id" required>
                @foreach($vendors as $vendor)
                    <option value="{{ $vendor->id }}" @selected(old('vendor_id', $product->vendor_id ?? '') == $vendor->id)>{{ $vendor->store_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Category</label>
            <select class="form-control" name="category_id">
                <option value="">—</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id ?? '') == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
        <div>
            <label class="form-label">Status</label>
            <select class="form-control" name="status">
                @foreach($statuses as $st)
                    <option value="{{ $st }}" @selected(old('status', $product->status ?? 'draft') === $st)>{{ str_replace('_',' ', ucfirst($st)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Stock {{ auth()->user()->hasPermission('inventory.adjust') ? '' : '(view)' }}</label>
            <input class="form-control" type="number" min="0" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" @disabled(!auth()->user()->hasPermission('inventory.adjust') && $editing)>
            @unless(auth()->user()->hasPermission('inventory.adjust'))
                <small style="color:var(--color-ink-muted);">Stock changes require inventory.adjust</small>
            @endunless
        </div>
        <div>
            <label class="form-label">Reorder level</label>
            <input class="form-control" type="number" min="0" name="reorder_level" value="{{ old('reorder_level', $product->reorder_level ?? 5) }}">
        </div>
    </div>

    <div>
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="5">{{ old('description', $product->description ?? '') }}</textarea>
    </div>

    <div>
        <label class="form-label">Image</label>
        <input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
    </div>

    <label style="display:flex;gap:.5rem;align-items:center;">
        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured ?? false))>
        Featured
    </label>
    <label style="display:flex;gap:.5rem;align-items:center;">
        <input type="checkbox" name="is_new" value="1" @checked(old('is_new', $product->is_new ?? false))>
        New
    </label>

    <button class="btn btn-primary" type="submit">{{ $editing ? 'Save changes' : 'Create product' }}</button>
</form>
@endsection
