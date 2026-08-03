@extends('layouts.app')
@section('title', 'Ledger')
@section('content')
@include('admin._nav')
@include('admin.finance._nav')
<h1 class="font-display">Ledger</h1>
<p style="color:var(--color-ink-muted);">Append-only double-entry journal (TZS). Historical rows are immutable.</p>
@foreach($transactions as $txn)
<div class="panel" style="margin-top:1rem;">
    <strong>{{ $txn->reference }}</strong>
    <div style="color:var(--color-ink-muted);font-size:.9rem;">
        {{ $txn->type }} · {{ $txn->currency }} · {{ $txn->posted_at?->format('M d, Y H:i') }}
        @if($txn->order) · Order {{ $txn->order->order_number }} @endif
    </div>
    <table style="width:100%;margin-top:.75rem;border-collapse:collapse;">
        <thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);"><th>Account</th><th>Vendor</th><th>Debit</th><th>Credit</th></tr></thead>
        <tbody>
        @foreach($txn->entries as $entry)
            <tr style="border-bottom:1px solid var(--color-border);">
                <td style="padding:.4rem 0;">{{ $entry->account->code ?? '—' }}</td>
                <td>{{ $entry->vendor_id ?: '—' }}</td>
                <td>{{ money($entry->debit) }}</td>
                <td>{{ money($entry->credit) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endforeach
{{ $transactions->links() }}
@endsection
