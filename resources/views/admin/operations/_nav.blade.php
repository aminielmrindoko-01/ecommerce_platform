<nav class="chip-row section" aria-label="Operations">
    @if(auth()->user()?->hasPermission('returns.view'))
        <a class="chip {{ request()->routeIs('admin.operations.returns*') ? 'is-active' : '' }}" href="{{ route('admin.operations.returns') }}">Returns</a>
    @endif
    @if(auth()->user()?->hasPermission('disputes.view'))
        <a class="chip {{ request()->routeIs('admin.operations.disputes*') ? 'is-active' : '' }}" href="{{ route('admin.operations.disputes') }}">Disputes</a>
    @endif
    @if(auth()->user()?->hasPermission('chargebacks.view'))
        <a class="chip {{ request()->routeIs('admin.operations.chargebacks*') ? 'is-active' : '' }}" href="{{ route('admin.operations.chargebacks') }}">Chargebacks</a>
    @endif
    @if(auth()->user()?->hasPermission('settlement_holds.view'))
        <a class="chip {{ request()->routeIs('admin.operations.holds*') ? 'is-active' : '' }}" href="{{ route('admin.operations.holds') }}">Settlement Holds</a>
    @endif
    @if(auth()->user()?->hasPermission('commission.manage') || auth()->user()?->hasPermission('finance.reports.view'))
        <a class="chip {{ request()->routeIs('admin.operations.commission*') ? 'is-active' : '' }}" href="{{ route('admin.operations.commission') }}">Commission</a>
    @endif
</nav>
