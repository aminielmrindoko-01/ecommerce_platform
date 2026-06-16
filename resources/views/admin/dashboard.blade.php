@extends('layouts.app')

@section('content')

<div class="page-header">
    <h1>Admin Dashboard</h1>
    <p>Full system overview and quick access to administration controls.</p>
</div>

<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <a href="{{ route('admin.products') }}" class="btn btn-primary">Products</a>
    <a href="{{ route('admin.vendors') }}" class="btn btn-secondary">Vendors</a>
    <a href="{{ route('admin.users') }}" class="btn btn-primary">Users</a>
    <a href="{{ route('admin.orders') }}" class="btn btn-secondary">Orders</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;">
    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h3>Total Users</h3>
        <p style="font-size:2rem;font-weight:700;margin-top:12px;">{{ $totalUsers }}</p>
    </div>
    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h3>Total Products</h3>
        <p style="font-size:2rem;font-weight:700;margin-top:12px;">{{ $totalProducts }}</p>
    </div>
    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h3>Total Vendors</h3>
        <p style="font-size:2rem;font-weight:700;margin-top:12px;">{{ $totalVendors }}</p>
    </div>
    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h3>Total Orders</h3>
        <p style="font-size:2rem;font-weight:700;margin-top:12px;">{{ $totalOrders }}</p>
    </div>
</div>

<div style="margin-top:32px;display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <div style="background:white;padding:24px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h2>Latest Products</h2>
        @foreach($recentProducts as $product)
            <div style="padding:14px 0;border-bottom:1px solid #e5e7eb;">
                <strong>{{ $product->name }}</strong>
                <p style="margin:6px 0;color:#6b7280;">{{ $product->vendor->store_name ?? 'Vendor' }} · TSh {{ number_format($product->price,0) }}</p>
            </div>
        @endforeach
    </div>
    <div style="background:white;padding:24px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h2>Recent Orders</h2>
        @foreach($recentOrders as $order)
            <div style="padding:14px 0;border-bottom:1px solid #e5e7eb;">
                <strong>Order #{{ $order->id }}</strong>
                <p style="margin:6px 0;color:#6b7280;">{{ $order->user->name ?? 'Guest' }} · {{ ucfirst($order->status) }}</p>
            </div>
        @endforeach
    </div>
</div>

@endsection