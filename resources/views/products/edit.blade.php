@extends('layouts.app')
@section('title', 'Edit product')
@section('content')
<div class="panel" style="max-width:720px;margin:0 auto;">
    <h1 class="font-display" style="margin-top:0;">Edit product</h1>
    <form action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="vendor_id">Vendor</label>
            <select class="form-control" id="vendor_id" name="vendor_id" required>
                @foreach($vendors as $vendor)
                    <option value="{{ $vendor->id }}" @selected(old('vendor_id', $product->vendor_id) == $vendor->id)>{{ $vendor->store_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="category_id">Category</label>
            <select class="form-control" id="category_id" name="category_id">
                <option value="">Optional</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="name">Name</label>
            <input class="form-control" id="name" name="name" value="{{ old('name', $product->name) }}" required>
        </div>
        <div class="form-group">
            <label for="brand">Brand</label>
            <input class="form-control" id="brand" name="brand" value="{{ old('brand', $product->brand) }}">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
            <div class="form-group"><label for="price">Price</label><input class="form-control" type="number" id="price" name="price" value="{{ old('price', $product->price) }}" step="0.01" min="0" required></div>
            <div class="form-group"><label for="stock">Stock</label><input class="form-control" type="number" id="stock" name="stock" value="{{ old('stock', $product->stock) }}" min="0" required></div>
        </div>
        <div class="form-group"><label for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $product->description) }}</textarea></div>
        <div class="form-group">
            <label for="image">Image</label>
            <img src="{{ $product->image_url }}" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:10px;margin-bottom:.5rem;display:block;">
            <input class="form-control" type="file" id="image" name="image" accept="image/*">
        </div>
        <div style="display:flex;gap:.5rem;">
            <a class="btn btn-ghost" href="{{ route('products.show', $product->id) }}">Cancel</a>
            <button class="btn btn-primary" type="submit">Save changes</button>
        </div>
    </form>
</div>
@endsection
