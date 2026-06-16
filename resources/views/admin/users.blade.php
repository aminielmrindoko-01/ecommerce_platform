@extends('layouts.app')

@section('content')

<div class="page-header">
    <h1>Manage Users</h1>
    <p>Update roles and manage access across the platform.</p>
</div>

<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Dashboard</a>
    <a href="{{ route('admin.products') }}" class="btn btn-primary">Products</a>
    <a href="{{ route('admin.vendors') }}" class="btn btn-primary">Vendors</a>
    <a href="{{ route('admin.orders') }}" class="btn btn-secondary">Orders</a>
</div>

<div style="background:white;padding:24px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f3f4f6;text-align:left;">
                <th style="padding:14px;">Name</th>
                <th style="padding:14px;">Email</th>
                <th style="padding:14px;">Role</th>
                <th style="padding:14px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                <tr style="border-top:1px solid #e5e7eb;">
                    <td style="padding:14px;">{{ $user->name }}</td>
                    <td style="padding:14px;">{{ $user->email }}</td>
                    <td style="padding:14px;">{{ $user->role }}</td>
                    <td style="padding:14px;">
                        <form action="{{ route('admin.users.update', $user->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('PUT')
                            <select name="role" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;margin-right:8px;">
                                <option value="customer" {{ $user->role === 'customer' ? 'selected' : '' }}>Customer</option>
                                <option value="vendor" {{ $user->role === 'vendor' ? 'selected' : '' }}>Vendor</option>
                                <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
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