@extends('layouts.app')
@section('title', 'Return '.$return->reference)
@section('content')
@include('account._nav')
<div class="panel">
    <h1 class="font-display" style="margin-top:0;">{{ $return->reference }}</h1>
    <p>Status: <strong>{{ ucfirst($return->status) }}</strong> · Order {{ $return->order?->order_number }}</p>
    <p style="color:var(--color-ink-muted);">{{ $return->reason }}</p>
    @foreach($return->items as $item)
        <div style="padding:.5rem 0;border-bottom:1px solid var(--color-border);">
            {{ $item->orderItem?->displayName() }} × {{ $item->quantity }} · {{ money($item->line_amount) }}
        </div>
    @endforeach
    @if($return->paymentRefund)
        <p style="margin-top:1rem;">Refund: {{ $return->paymentRefund->reference }} · {{ money($return->paymentRefund->amount) }} · {{ $return->paymentRefund->status }}</p>
    @endif
    @if(in_array($return->status, ['requested','approved'], true))
        <form method="POST" action="{{ route('account.returns.cancel', $return) }}" style="margin-top:1rem;" onsubmit="return confirm('Cancel this return?');">
            @csrf
            <button class="btn btn-ghost" type="submit">Cancel return</button>
        </form>
    @endif
</div>
@endsection
