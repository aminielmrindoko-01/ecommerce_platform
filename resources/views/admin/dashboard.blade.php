@extends('layouts.app')
@section('title', 'Admin dashboard')
@section('content')
@include('admin._nav')

<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Admin analytics</h1>
        <p>Marketplace health, sales, and inventory signals</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;" class="section">
    <div class="admin-stat"><span>Revenue</span><strong>TSh {{ number_format($revenue, 0) }}</strong></div>
    <div class="admin-stat"><span>Orders</span><strong>{{ $totalOrders }}</strong></div>
    <div class="admin-stat"><span>Pending</span><strong>{{ $pendingOrders }}</strong></div>
    <div class="admin-stat"><span>Products</span><strong>{{ $totalProducts }}</strong></div>
    <div class="admin-stat"><span>Users</span><strong>{{ $totalUsers }}</strong></div>
    <div class="admin-stat"><span>Vendors</span><strong>{{ $totalVendors }}</strong></div>
    <div class="admin-stat"><span>Low stock</span><strong>{{ $lowStock }}</strong></div>
    <div class="admin-stat"><span>Avg rating</span><strong>{{ $avgRating }}</strong></div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:1.25rem;" class="section">
    <div class="panel">
        <h2 style="margin-top:0;font-size:1.1rem;">Sales (7 days)</h2>
        <canvas id="salesChart" height="120" aria-label="Sales chart"></canvas>
    </div>
    <div class="panel">
        <h2 style="margin-top:0;font-size:1.1rem;">Orders by status</h2>
        <canvas id="statusChart" height="120" aria-label="Status chart"></canvas>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
    <div class="panel">
        <h2 style="margin-top:0;font-size:1.1rem;">Latest products</h2>
        @foreach($recentProducts as $product)
            <div style="padding:.7rem 0;border-bottom:1px solid var(--color-border);">
                <strong>{{ $product->name }}</strong>
                <div style="color:var(--color-ink-muted);font-size:.9rem;">{{ $product->vendor->store_name ?? 'Vendor' }} · TSh {{ number_format($product->price,0) }}</div>
            </div>
        @endforeach
    </div>
    <div class="panel">
        <h2 style="margin-top:0;font-size:1.1rem;">Recent orders</h2>
        @forelse($recentOrders as $order)
            <div style="padding:.7rem 0;border-bottom:1px solid var(--color-border);">
                <strong>{{ $order->order_number ?? '#'.$order->id }}</strong>
                <div style="color:var(--color-ink-muted);font-size:.9rem;">{{ $order->user->name ?? 'Guest' }} · {{ ucfirst($order->status) }}</div>
            </div>
        @empty
            <p style="color:var(--color-ink-muted);">No orders yet.</p>
        @endforelse
    </div>
</div>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const labels = @json($chartLabels);
    const data = @json($chartData);
    const statusLabels = @json($ordersByStatus->keys()->values());
    const statusData = @json($ordersByStatus->values());

    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Revenue (TSh)',
                data,
                borderColor: '#0d7377',
                backgroundColor: 'rgba(13,115,119,.15)',
                fill: true,
                tension: 0.35,
            }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels.length ? statusLabels : ['none'],
            datasets: [{
                data: statusData.length ? statusData : [1],
                backgroundColor: ['#e89b1e', '#0d7377', '#5b6b78', '#0f7a4a']
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
});
</script>
@endpush
