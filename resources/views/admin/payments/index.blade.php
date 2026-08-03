@extends('layouts.app')
@section('title', 'Payments')
@section('content')
@include('admin._nav')
@php use App\Support\Payments\PaymentStatusPresenter; @endphp
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Payments</h1>
        <p>Provider-independent payment attempts (no secrets shown)</p>
    </div>
</div>

<form method="GET" class="panel" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;">
    <input class="form-control" style="max-width:220px;" type="search" name="q" value="{{ request('q') }}" placeholder="Reference / order / customer">
    <select class="form-control" style="max-width:180px;" name="status">
        <option value="">All statuses</option>
        @foreach(['pending','initiated','processing','paid','failed','cancelled','expired','refunded','partially_refunded'] as $st)
            <option value="{{ $st }}" @selected(request('status')===$st)>{{ PaymentStatusPresenter::label($st) }}</option>
        @endforeach
    </select>
    <select class="form-control" style="max-width:140px;" name="provider">
        <option value="">All providers</option>
        @foreach(['manual','stub','pesapal'] as $p)
            <option value="{{ $p }}" @selected(request('provider')===$p)>{{ $p }}</option>
        @endforeach
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>

@if(session('error'))<div class="panel" style="border-color:#b91c1c;margin-bottom:1rem;">{{ session('error') }}</div>@endif
@if(session('success'))<div class="panel" style="margin-bottom:1rem;">{{ session('success') }}</div>@endif

<div class="panel" style="overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:960px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">Reference</th><th>Order</th><th>Customer</th><th>Amount</th><th>Provider</th><th>Status</th><th>Attempt</th><th>Created</th><th>Completed</th><th></th>
</tr></thead>
<tbody>
@foreach($transactions as $tx)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;"><strong>{{ $tx->reference }}</strong>
    @if($tx->provider_reference)<div style="font-size:.8rem;color:var(--color-ink-muted);">{{ $tx->provider_reference }}</div>@endif
</td>
<td>{{ $tx->order?->order_number ?? '#'.$tx->order_id }}</td>
<td>{{ $tx->order?->user?->name ?? '—' }}</td>
<td>{{ money($tx->amount) }} {{ $tx->currency }}</td>
<td>{{ $tx->provider }}</td>
<td><span class="badge">{{ PaymentStatusPresenter::label($tx->status) }}</span></td>
<td>{{ $tx->attempt_number ?? 1 }}</td>
<td>{{ $tx->created_at?->format('M d, Y H:i') }}</td>
<td>{{ $tx->completed_at?->format('M d, Y H:i') ?? ($tx->paid_at?->format('M d, Y H:i') ?? '—') }}</td>
<td>
@canPermission('refunds.create')
@if(in_array($tx->status, ['paid','partially_refunded'], true) && bccomp((string)$tx->remainingRefundable(), '0.00', 2) > 0)
<form method="POST" action="{{ route('admin.payments.refund', $tx) }}" style="display:grid;gap:.25rem;min-width:180px;">
    @csrf
    <input class="form-control" name="amount" placeholder="Amount (max {{ $tx->remainingRefundable() }})" required>
    <input class="form-control" name="reason" placeholder="Reason" required maxlength="500">
    <button class="btn btn-ghost" type="submit" style="padding:.3rem .55rem;">Refund</button>
</form>
@endif
@endcanPermission
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $transactions->links() }}</div>
@endsection
