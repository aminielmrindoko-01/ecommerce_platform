@extends('layouts.app')
@section('title', 'Payment history')
@section('content')
@include('account._nav')
@php use App\Support\Payments\PaymentStatusPresenter; @endphp
<div class="panel">
    <h1 class="font-display" style="margin-top:0;">Payment history</h1>
    <p style="color:var(--color-ink-muted);">Your payment attempts for SANA Market orders.</p>
</div>

@forelse($payments as $payment)
    <div class="panel" style="margin-top:1rem;">
        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <div>
                <strong>{{ $payment->order?->order_number ?? 'Order #'.$payment->order_id }}</strong>
                <div style="color:var(--color-ink-muted);font-size:.9rem;">
                    {{ PaymentStatusPresenter::label($payment->status) }}
                    · Attempt {{ $payment->attempt_number ?? 1 }}
                    · {{ $payment->created_at?->format('M d, Y H:i') }}
                </div>
            </div>
            <div style="text-align:right;">
                <strong>{{ money($payment->amount) }} {{ $payment->currency }}</strong>
                <div style="color:var(--color-ink-muted);font-size:.85rem;">{{ $payment->reference }}</div>
            </div>
        </div>
        @if($payment->order)
            <a href="{{ route('account.orders.show', $payment->order) }}" style="font-size:.9rem;">View order</a>
        @endif
    </div>
@empty
    <div class="panel" style="margin-top:1rem;color:var(--color-ink-muted);">No payment attempts yet.</div>
@endforelse

{{ $payments->links() }}
@endsection
