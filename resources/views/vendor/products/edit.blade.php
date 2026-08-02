@extends('layouts.app')
@section('title', 'Edit product')
@section('content')
@include('vendor._nav')

<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Edit product</h1>
        <p>{{ $product->name }}</p>
    </div>
</div>

<form method="POST" action="{{ route('vendor.products.update', $product) }}" enctype="multipart/form-data" class="panel section" style="max-width:640px;">
    @csrf @method('PUT')
    <div class="form-group">
        <label for="name">Name</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $product->name) }}" required>
        @error('name')<div class="form-error">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label for="brand">Brand</label>
        <input class="form-control" id="brand" name="brand" value="{{ old('brand', $product->brand) }}">
    </div>
    <div class="form-group">
        <label for="price">Price (TZS)</label>
        <input class="form-control" id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', $product->price) }}" required>
        @error('price')<div class="form-error">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label for="stock">Stock</label>
        <input class="form-control" id="stock" name="stock" type="number" min="0" value="{{ old('stock', $product->stock) }}" required>
        @error('stock')<div class="form-error">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label for="category_id">Category</label>
        <select class="form-control" id="category_id" name="category_id">
            <option value="">— Optional —</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $product->description) }}</textarea>
    </div>
    <div class="form-group">
        <label for="image">Replace image (optional)</label>
        <input class="form-control" id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
        @error('image')<div class="form-error">{{ $message }}</div>@enderror
    </div>
    <button class="btn btn-primary" type="submit">Save changes</button>
</form>
@endsection
