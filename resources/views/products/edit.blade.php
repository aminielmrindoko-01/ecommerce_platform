@extends('layouts.app')

@section('content')

<div class="page-header">
    <h1>✏️ Edit Product</h1>
    <p>Update product information</p>
</div>

<div style="background:white;padding:32px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);max-width:700px;margin:0 auto;">
    <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="vendor_id">Vendor *</label>
            <select id="vendor_id" name="vendor_id" required>
                <option value="">Select a vendor</option>
                @foreach($vendors as $vendor)
                    <option value="{{ $vendor->id }}" {{ old('vendor_id', $product->vendor_id) == $vendor->id ? 'selected' : '' }}>{{ $vendor->store_name }}</option>
                @endforeach
            </select>
            @error('vendor_id')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="name">Product Name *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" placeholder="Enter product name" required>
            @error('name')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="price">Price (TSh) *</label>
            <input type="number" id="price" name="price" value="{{ old('price', $product->price) }}" placeholder="Enter product price" step="0.01" min="0" required>
            @error('price')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="stock">Stock Quantity *</label>
            <input type="number" id="stock" name="stock" value="{{ old('stock', $product->stock) }}" placeholder="Enter available quantity" min="0" required>
            @error('stock')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="Enter detailed product description...">{{ old('description', $product->description) }}</textarea>
            @error('description')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="image">Product Image</label>
            @if($product->image)
                <div style="margin-bottom:12px;">
                    <img src="{{ asset('images/'.$product->image) }}" alt="{{ $product->name }}" style="max-width:200px;border-radius:8px;">
                </div>
            @endif
            <input type="file" id="image" name="image" accept="image/*">
            @error('image')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:24px;">
            <a href="{{ route('products.show', $product->id) }}" class="btn btn-primary" style="text-decoration:none;text-align:center;">Cancel</a>
            <button type="submit" class="btn btn-secondary" style="border:none;cursor:pointer;">Save Changes</button>
        </div>
    </form>
</div>

@endsection