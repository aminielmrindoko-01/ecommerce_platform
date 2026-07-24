<nav class="chip-row section" aria-label="Admin">
    <a class="chip {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
    <a class="chip {{ request()->routeIs('admin.products') ? 'is-active' : '' }}" href="{{ route('admin.products') }}">Products</a>
    <a class="chip {{ request()->routeIs('admin.inventory') ? 'is-active' : '' }}" href="{{ route('admin.inventory') }}">Inventory</a>
    <a class="chip {{ request()->routeIs('admin.orders') ? 'is-active' : '' }}" href="{{ route('admin.orders') }}">Orders</a>
    <a class="chip {{ request()->routeIs('admin.users') ? 'is-active' : '' }}" href="{{ route('admin.users') }}">Customers</a>
    <a class="chip {{ request()->routeIs('admin.vendors') ? 'is-active' : '' }}" href="{{ route('admin.vendors') }}">Vendors</a>
    <a class="chip {{ request()->routeIs('admin.categories') ? 'is-active' : '' }}" href="{{ route('admin.categories') }}">Categories</a>
    <a class="chip {{ request()->routeIs('admin.coupons') ? 'is-active' : '' }}" href="{{ route('admin.coupons') }}">Coupons</a>
    <a class="chip {{ request()->routeIs('admin.reviews') ? 'is-active' : '' }}" href="{{ route('admin.reviews') }}">Reviews</a>
</nav>
