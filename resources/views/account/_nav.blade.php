<nav class="chip-row section" aria-label="Account">
    <a class="chip {{ request()->routeIs('account.index') ? 'is-active' : '' }}" href="{{ route('account.index') }}">Overview</a>
    <a class="chip {{ request()->routeIs('account.orders*') ? 'is-active' : '' }}" href="{{ route('account.orders') }}">Orders</a>
    <a class="chip {{ request()->routeIs('account.addresses*') ? 'is-active' : '' }}" href="{{ route('account.addresses') }}">Addresses</a>
    <a class="chip {{ request()->routeIs('wishlist.*') || request()->routeIs('account.wishlist') ? 'is-active' : '' }}" href="{{ route('wishlist.index') }}">Wishlist</a>
    <a class="chip {{ request()->routeIs('account.notifications') ? 'is-active' : '' }}" href="{{ route('account.notifications') }}">Notifications</a>
    <a class="chip {{ request()->routeIs('account.security') ? 'is-active' : '' }}" href="{{ route('account.security') }}">Security</a>
</nav>
