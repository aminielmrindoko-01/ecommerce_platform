{{-- Admin section navigation — permission-aware (UX only; backend enforces). --}}
@php
    $user = auth()->user();
@endphp
<nav class="chip-row section" aria-label="Admin">
    @if($user?->hasPermission('dashboard.view'))
        <a class="chip {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
    @endif
    @if($user?->hasPermission('products.view'))
        <a class="chip {{ request()->routeIs('admin.products.*') ? 'is-active' : '' }}" href="{{ route('admin.products.index') }}">Products</a>
    @endif
    @if($user?->hasPermission('inventory.view'))
        <a class="chip {{ request()->routeIs('admin.inventory.*') ? 'is-active' : '' }}" href="{{ route('admin.inventory.index') }}">Inventory</a>
    @endif
    @if($user?->hasPermission('orders.view'))
        <a class="chip {{ request()->routeIs('admin.orders') ? 'is-active' : '' }}" href="{{ route('admin.orders') }}">Orders</a>
    @endif
    @if($user?->hasPermission('returns.view') || $user?->hasPermission('disputes.view') || $user?->hasPermission('chargebacks.view') || $user?->hasPermission('settlement_holds.view'))
        <a class="chip {{ request()->routeIs('admin.operations.*') ? 'is-active' : '' }}" href="{{ route('admin.operations.returns') }}">Operations</a>
    @endif
    @if($user?->hasPermission('payments.view') || $user?->hasPermission('transactions.view') || $user?->hasPermission('ledger.view') || $user?->hasPermission('payouts.view'))
        <a class="chip {{ request()->routeIs('admin.payments.*') || request()->routeIs('admin.finance.*') ? 'is-active' : '' }}" href="{{ route('admin.payments.index') }}">Finance</a>
    @endif
    @if($user?->hasPermission('customers.view') || $user?->hasPermission('users.view'))
        <a class="chip {{ request()->routeIs('admin.users') ? 'is-active' : '' }}" href="{{ route('admin.users') }}">Customers</a>
    @endif
    @if($user?->hasPermission('vendors.view'))
        <a class="chip {{ request()->routeIs('admin.vendors') ? 'is-active' : '' }}" href="{{ route('admin.vendors') }}">Vendors</a>
    @endif
    @if($user?->hasPermission('categories.view'))
        <a class="chip {{ request()->routeIs('admin.categories.*') ? 'is-active' : '' }}" href="{{ route('admin.categories.index') }}">Categories</a>
    @endif
    @if($user?->hasPermission('coupons.view'))
        <a class="chip {{ request()->routeIs('admin.coupons') ? 'is-active' : '' }}" href="{{ route('admin.coupons') }}">Coupons</a>
    @endif
    @if($user?->hasPermission('reviews.view'))
        <a class="chip {{ request()->routeIs('admin.reviews') ? 'is-active' : '' }}" href="{{ route('admin.reviews') }}">Reviews</a>
    @endif
    @if($user?->hasPermission('roles.view'))
        <a class="chip {{ request()->routeIs('admin.roles') ? 'is-active' : '' }}" href="{{ route('admin.roles') }}">Roles</a>
    @endif
    @if($user?->hasPermission('audit_logs.view'))
        <a class="chip {{ request()->routeIs('admin.audit-logs') ? 'is-active' : '' }}" href="{{ route('admin.audit-logs') }}">Audit</a>
    @endif
    @if($user?->hasPermission('security_events.view'))
        <a class="chip {{ request()->routeIs('admin.security-events') ? 'is-active' : '' }}" href="{{ route('admin.security-events') }}">Security</a>
    @endif
</nav>
