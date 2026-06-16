@extends('layouts.app')

@section('content')

<div class="page-header">
    <h1>Manage Orders</h1>
    <p>Review order status, customer details, and update shipment progress.</p>
</div>

<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Dashboard</a>
    <a href="{{ route('admin.products') }}" class="btn btn-primary">Products</a>
    <a href="{{ route('admin.vendors') }}" class="btn btn-primary">Vendors</a>
    <a href="{{ route('admin.users') }}" class="btn btn-secondary">Users</a>
</div>

<div style="background:white;padding:24px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f3f4f6;text-align:left;">
                <th style="padding:14px;">Order</th>
                <th style="padding:14px;">Customer</th>
                <th style="padding:14px;">Total</th>
                <th style="padding:14px;">Status</th>
                <th style="padding:14px;">Update</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $order)
                <tr style="border-top:1px solid #e5e7eb;">
                    <td style="padding:14px;">#{{ $order->id }}</td>
                    <td style="padding:14px;">{{ $order->user->name ?? 'Guest' }}</td>
                    <td style="padding:14px;">TSh {{ number_format($order->total,0) }}</td>
                    <td style="padding:14px;">{{ ucfirst($order->status) }}</td>
                    <td style="padding:14px;">
                        <form action="{{ route('admin.orders.update', $order->id) }}" method="POST" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            @csrf
                            @method('PUT')
                            <select name="status" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;">
                                <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="paid" {{ $order->status === 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="shipped" {{ $order->status === 'shipped' ? 'selected' : '' }}>Shipped</option>
                                <option value="completed" {{ $order->status === 'completed' ? 'selected' : '' }}>Completed</option>
                            </select>
                            <button type="submit" class="btn btn-primary" style="border:none;">Save</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection