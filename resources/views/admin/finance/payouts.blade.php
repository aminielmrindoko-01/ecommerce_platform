@extends('layouts.app')
@section('title', 'Payouts')
@section('content')
@include('admin._nav')
@include('admin.finance._nav')
<h1 class="font-display">Payouts</h1>
<p style="color:var(--color-ink-muted);">PAYOUT STATUS: SANDBOX / NOT PRODUCTION-READY</p>
@if(session('error'))<div class="panel" style="border-color:#b91c1c;">{{ session('error') }}</div>@endif
@if(session('success'))<div class="panel">{{ session('success') }}</div>@endif
<form method="GET" class="panel" style="display:flex;gap:.5rem;">
    <select class="form-control" name="status" style="max-width:180px;">
        <option value="">All</option>
        @foreach(['pending','approved','processing','completed','failed','cancelled','rejected'] as $st)
            <option value="{{ $st }}" @selected(request('status')===$st)>{{ $st }}</option>
        @endforeach
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>
@foreach($payouts as $payout)
<div class="panel" style="margin-top:1rem;">
    <strong>{{ $payout->reference }}</strong> · {{ $payout->vendor?->store_name }}
    <div>{{ money($payout->amount) }} {{ $payout->currency }} · <span class="badge">{{ $payout->status }}</span></div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem;">
        @if($payout->status==='pending')
            @canPermission('payouts.approve')
            <form method="POST" action="{{ route('admin.finance.payouts.approve', $payout) }}">@csrf<button class="btn btn-primary" type="submit">Approve</button></form>
            @endcanPermission
            @canPermission('payouts.reject')
            <form method="POST" action="{{ route('admin.finance.payouts.reject', $payout) }}" style="display:flex;gap:.35rem;">@csrf
                <input class="form-control" name="reason" placeholder="Reason" required>
                <button class="btn btn-ghost" type="submit">Reject</button>
            </form>
            @endcanPermission
        @endif
        @if($payout->status==='approved')
            @canPermission('payouts.process')
            <form method="POST" action="{{ route('admin.finance.payouts.process', $payout) }}">@csrf<button class="btn btn-primary" type="submit">Process</button></form>
            @endcanPermission
        @endif
    </div>
</div>
@endforeach
{{ $payouts->links() }}
@endsection
