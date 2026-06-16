@extends('layouts.app')

@section('content')

<div class="page-header">
    <h1>Manage Products</h1>
    <p>Review, edit, or remove products from the store.</p>
</div>

<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Dashboard</a>
    <a href="{{ route('admin.vendors') }}" class="btn btn-primary">Vendors</a>
    <a href="{{ route('admin.users') }}" class="btn btn-primary">Users</a>
    <a href="{{ route('admin.orders') }}" class="btn btn-secondary">Orders</a>
</div>

<div style="background:white;padding:24px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f3f4f6;text-align:left;">
                <th style="padding:14px;">Product</th>
                <th style="padding:14px;">Vendor</th>
                <th style="padding:14px;">Stock</th>
                <th style="padding:14px;">Price</th>
                <th style="padding:14px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
                <tr style="border-top:1px solid #e5e7eb;">
                    <td style="padding:14px;">{{ $product->name }}</td>
                    <td style="padding:14px;">{{ $product->vendor->store_name ?? 'Unknown' }}</td>
                    <td style="padding:14px;">{{ $product->stock }}</td>
                    <td style="padding:14px;">TSh {{ number_format($product->price,0) }}</td>
                    <td style="padding:14px;">
                        <a href="{{ route('products.edit', $product->id) }}" class="btn btn-primary" style="margin-right:8px;">Edit</a>
                        <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn" style="background:#ef4444;color:white;border:none;">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection