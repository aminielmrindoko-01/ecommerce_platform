<nav class="chip-row section" aria-label="Finance">
    @if(auth()->user()?->hasPermission('payments.view') || auth()->user()?->hasPermission('transactions.view'))
        <a class="chip {{ request()->routeIs('admin.payments.*') ? 'is-active' : '' }}" href="{{ route('admin.payments.index') }}">Transactions</a>
    @endif
    @if(auth()->user()?->hasPermission('ledger.view'))
        <a class="chip {{ request()->routeIs('admin.finance.ledger') ? 'is-active' : '' }}" href="{{ route('admin.finance.ledger') }}">Ledger</a>
    @endif
    @if(auth()->user()?->hasPermission('payments.view'))
        <a class="chip {{ request()->routeIs('admin.payments.refunds') ? 'is-active' : '' }}" href="{{ route('admin.payments.refunds') }}">Refunds</a>
    @endif
    @if(auth()->user()?->hasPermission('payouts.view') || auth()->user()?->hasPermission('finance.reports.view'))
        <a class="chip {{ request()->routeIs('admin.finance.payables') ? 'is-active' : '' }}" href="{{ route('admin.finance.payables') }}">Payables</a>
    @endif
    @if(auth()->user()?->hasPermission('payouts.view'))
        <a class="chip {{ request()->routeIs('admin.finance.payouts') ? 'is-active' : '' }}" href="{{ route('admin.finance.payouts') }}">Payouts</a>
    @endif
    @if(auth()->user()?->hasPermission('ledger.view') || auth()->user()?->hasPermission('finance.reports.view'))
        <a class="chip {{ request()->routeIs('admin.finance.entitlements') ? 'is-active' : '' }}" href="{{ route('admin.finance.entitlements') }}">Entitlements</a>
    @endif
    @if(auth()->user()?->hasPermission('finance.reports.view'))
        <a class="chip {{ request()->routeIs('admin.finance.reports') ? 'is-active' : '' }}" href="{{ route('admin.finance.reports') }}">Reports</a>
    @endif
    @if(auth()->user()?->hasPermission('payments.view'))
        <a class="chip {{ request()->routeIs('admin.payments.reconciliations') ? 'is-active' : '' }}" href="{{ route('admin.payments.reconciliations') }}">Reconciliation</a>
    @endif
</nav>
