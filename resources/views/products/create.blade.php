@extends('layouts.app')
@section('title', 'Add product')
@section('content')
<div class="panel" style="max-width:720px;margin:0 auto;">
    <h1 class="font-display" style="margin-top:0;">Add product</h1>
    <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label for="vendor_id">Vendor</label>
            <select class="form-control" id="vendor_id" name="vendor_id" required>
                <option value="">Select vendor</option>
                @foreach($vendors as $vendor)
                    <option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>{{ $vendor->store_name }}</option>
                @endforeach
            </select>
            @error('vendor_id')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
            <label for="category_id">Category</label>
            <select class="form-control" id="category_id" name="category_id">
                <option value="">Optional</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="name">Name</label>
            <input class="form-control" id="name" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="form-group">
            <label for="brand">Brand</label>
            <input class="form-control" id="brand" name="brand" value="{{ old('brand') }}">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
            <div class="form-group"><label for="price">Price (TSh)</label><input class="form-control" type="number" id="price" name="price" value="{{ old('price') }}" step="0.01" min="0" required></div>
            <div class="form-group"><label for="stock">Stock</label><input class="form-control" type="number" id="stock" name="stock" value="{{ old('stock') }}" min="0" required></div>
        </div>
        <div class="form-group"><label for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4">{{ old('description') }}</textarea></div>
        <div class="form-group"><label for="image">Image</label><input class="form-control" type="file" id="image" name="image" accept="image/*"></div>
        <div style="display:flex;gap:.5rem;">
            <a class="btn btn-ghost" href="{{ route('products.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit">Create product</button>
        </div>
    </form>
</div>
@endsection
