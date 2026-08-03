{{-- Vendor section navigation chips --}}
<nav class="chip-row section" aria-label="Vendor">
    <a class="chip {{ request()->routeIs('vendor.dashboard') ? 'is-active' : '' }}" href="{{ route('vendor.dashboard') }}">Dashboard</a>
    <a class="chip {{ request()->routeIs('vendor.products.*') ? 'is-active' : '' }}" href="{{ route('vendor.products.index') }}">Products</a>
    <a class="chip {{ request()->routeIs('vendor.orders.*') ? 'is-active' : '' }}" href="{{ route('vendor.orders.index') }}">Orders</a>
    <a class="chip {{ request()->routeIs('vendor.finance.*') ? 'is-active' : '' }}" href="{{ route('vendor.finance.index') }}">Finance</a>
    <a class="chip {{ request()->routeIs('vendor.profile.*') ? 'is-active' : '' }}" href="{{ route('vendor.profile.edit') }}">Profile</a>
</nav>
