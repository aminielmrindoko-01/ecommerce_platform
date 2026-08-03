@extends('layouts.app')
@section('title', 'Finance reports')
@section('content')
@include('admin._nav')
@include('admin.finance._nav')
<h1 class="font-display">Finance reports</h1>
<p style="color:var(--color-ink-muted);">Derived from entitlements / payouts (ledger remains authoritative for payable).</p>
<div class="panel" style="display:grid;gap:.75rem;max-width:420px;margin-top:1rem;">
    <div style="display:flex;justify-content:space-between;"><span>Gross sales</span><strong>{{ money($report['gross_sales']) }} {{ $report['currency'] }}</strong></div>
    <div style="display:flex;justify-content:space-between;"><span>Platform commission</span><strong>{{ money($report['platform_commission']) }}</strong></div>
    <div style="display:flex;justify-content:space-between;"><span>Refunds (vendor net)</span><strong>{{ money($report['refunds_net']) }}</strong></div>
    <div style="display:flex;justify-content:space-between;"><span>Completed payouts</span><strong>{{ money($report['completed_payouts']) }}</strong></div>
    <div style="display:flex;justify-content:space-between;"><span>Pending payouts</span><strong>{{ money($report['pending_payouts']) }}</strong></div>
</div>
@endsection
