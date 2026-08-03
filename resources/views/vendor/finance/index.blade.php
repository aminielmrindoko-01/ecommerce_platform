@extends('layouts.app')
@section('title', 'Vendor finance')
@section('content')
@include('vendor._nav')
<h1 class="font-display">Finance</h1>
<p style="color:var(--color-ink-muted);">Your store balances only. PAYOUT STATUS: SANDBOX.</p>
@if(session('error'))<div class="panel" style="border-color:#b91c1c;">{{ session('error') }}</div>@endif
@if(session('success'))<div class="panel">{{ session('success') }}</div>@endif

<div class="panel" style="display:grid;gap:.55rem;max-width:420px;">
    <div style="display:flex;justify-content:space-between;"><span>Sales</span><strong>{{ money($summary['sales_gross']) }} {{ $summary['currency'] }}</strong></div>
    <div style="display:flex;justify-content:space-between;"><span>Commission</span><strong>{{ money($summary['commission']) }}</strong></div>
    <div style="display:flex;justify-content:space-between;"><span>Refunds</span><strong>{{ money($summary['refunds_net']) }}</strong></div>
    <div style="display:flex;justify-content:space-between;"><span>Payable</span><strong>{{ money($summary['payable_ledger']) }}</strong></div>
    <div style="display:flex;justify-content:space-between;"><span>Paid out</span><strong>{{ money($summary['paid_out']) }}</strong></div>
    <div style="display:flex;justify-content:space-between;"><span>Available</span><strong>{{ money($summary['available']) }}</strong></div>
</div>

<div class="panel" style="margin-top:1rem;max-width:420px;">
    <h2 style="margin-top:0;font-size:1.1rem;">Request payout</h2>
    <form method="POST" action="{{ route('vendor.finance.payout') }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <label>Amount ({{ $summary['currency'] }})</label>
        <input class="form-control" name="amount" required placeholder="{{ $summary['available'] }}">
        <button class="btn btn-primary" type="submit" style="margin-top:.75rem;">Request</button>
    </form>
</div>

<div class="panel" style="margin-top:1rem;">
    <h2 style="margin-top:0;font-size:1.1rem;">Recent entitlements</h2>
    @forelse($entitlements as $ent)
        <div style="padding:.5rem 0;border-bottom:1px solid var(--color-border);">
            {{ $ent->order?->order_number }} · Net {{ money($ent->net_amount) }} · {{ $ent->status }}
        </div>
    @empty
        <p style="color:var(--color-ink-muted);">No entitlements yet.</p>
    @endforelse
    {{ $entitlements->links() }}
</div>

<div class="panel" style="margin-top:1rem;">
    <h2 style="margin-top:0;font-size:1.1rem;">Payouts</h2>
    @forelse($payouts as $payout)
        <div style="padding:.5rem 0;border-bottom:1px solid var(--color-border);">
            {{ $payout->reference }} · {{ money($payout->amount) }} · {{ $payout->status }}
        </div>
    @empty
        <p style="color:var(--color-ink-muted);">No payouts yet.</p>
    @endforelse
    {{ $payouts->links() }}
</div>
@endsection
