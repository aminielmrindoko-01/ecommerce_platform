@extends('layouts.app')

@section('title', 'My account')

@section('content')
@include('account._nav')

<div style="display:grid;grid-template-columns:1.2fr .8fr;gap:1.25rem;align-items:start;">
    <section class="panel">
        <h1 class="font-display" style="margin-top:0;">Hello, {{ $user->name }}</h1>
        <p style="color:var(--color-ink-muted);">Manage orders, addresses, wishlist, and security.</p>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-top:1rem;">
            <div class="admin-stat"><span>Orders</span><strong>{{ $user->orders_count }}</strong></div>
            <div class="admin-stat"><span>Wishlist</span><strong>{{ $user->wishlists_count }}</strong></div>
            <div class="admin-stat"><span>Addresses</span><strong>{{ $user->addresses_count }}</strong></div>
        </div>

        <form method="POST" action="{{ route('account.profile.update') }}" enctype="multipart/form-data" style="margin-top:1.5rem;">
            @csrf
            @method('PUT')
            <h2 style="font-size:1.1rem;">Profile</h2>
            <div class="form-group">
                <label for="name">Name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
            </div>
            <div class="form-group">
                <label for="avatar">Avatar</label>
                <input class="form-control" id="avatar" name="avatar" type="file" accept="image/*">
            </div>
            <button class="btn btn-primary" type="submit">Save profile</button>
        </form>
    </section>

    <section class="panel">
        <h2 style="margin-top:0;font-size:1.1rem;">Recent orders</h2>
        @forelse($recentOrders as $order)
            <a href="{{ route('account.orders.show', $order) }}" style="display:block;padding:.75rem 0;border-bottom:1px solid var(--color-border);">
                <strong>{{ $order->order_number ?? '#'.$order->id }}</strong>
                <div style="color:var(--color-ink-muted);font-size:.9rem;">{{ ucfirst($order->status) }} · TSh {{ number_format($order->total_price, 0) }}</div>
            </a>
        @empty
            <p style="color:var(--color-ink-muted);">No orders yet.</p>
        @endforelse
        <a class="btn btn-ghost" href="{{ route('account.orders') }}" style="margin-top:1rem;width:100%;">All orders</a>
    </section>
</div>

<section class="section" style="margin-top:2rem;">
    <h2>Recently viewed</h2>
    <div class="products-grid" data-recently-viewed></div>
</section>

<style>
@media (max-width: 900px) {
    .site-main > div[style*="1.2fr"] { grid-template-columns: 1fr !important; }
    .panel .admin-stat { } 
}
</style>
@endsection
