{{--
  Reusable payment status panel (customer confirmation, account order, admin).
  Never render secrets / API keys / webhook material.
--}}
@php
    use App\Support\Payments\PaymentStatusPresenter;

    /** @var \App\Models\Order $order */
    /** @var array<string, mixed>|null $paymentInit */
    $paymentInit = $paymentInit ?? [];
    $context = $context ?? 'customer'; // customer|admin
    $payment = $payment ?? ($order->latestPaymentTransaction ?? null);

    $initStatus = $paymentInit['status'] ?? null;
    $methodLabel = $paymentInit['method_label']
        ?? (config('payments.methods.'.$order->payment_method.'.label') ?? strtoupper(str_replace('_', ' ', $order->payment_method ?? 'n/a')));
    $headline = $paymentInit['headline'] ?? 'Payment Service Coming Soon';
    $message = $paymentInit['message'] ?? 'Online payment is currently unavailable. No payment has been charged.';

    $paymentStatus = strtolower((string) ($order->payment_status ?? 'pending'));
    $statusLabel = PaymentStatusPresenter::label($paymentStatus);
    $gatewayDisplay = PaymentStatusPresenter::gatewayDisplayName(
        $paymentInit['provider'] ?? config('payments.methods.'.$order->payment_method.'.gateway')
    );

    $isPaid = $paymentStatus === 'paid';
    $isComingSoon = ! $isPaid && in_array($initStatus, ['coming_soon', 'unavailable', 'failed'], true);
    $showComingSoonCopy = $isComingSoon && in_array($paymentStatus, ['pending', 'processing'], true);

    $amount = $payment?->amount ?? $order->total_price;
    $currency = $payment?->currency ?? config('payments.currency', 'TZS');
    $updatedAt = $payment?->updated_at ?? $order->updated_at;
@endphp

<div class="panel payment-status-panel" style="text-align:left;margin:1.25rem 0;border:1px solid var(--color-border);{{ $context === 'admin' ? 'background:var(--color-surface);' : '' }}">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
        <div>
            <div style="font-size:.75rem;color:var(--color-ink-muted);text-transform:uppercase;letter-spacing:.04em;">Payment</div>
            <strong style="font-size:1.05rem;">{{ $methodLabel }}</strong>
            <div style="font-size:.85rem;color:var(--color-ink-muted);margin-top:.2rem;">
                Gateway: {{ $gatewayDisplay }}
            </div>
        </div>
        <div style="text-align:right;display:grid;gap:.35rem;justify-items:end;">
            @if($showComingSoonCopy)
                <span class="chip" style="background:var(--color-surface);">Coming Soon</span>
            @endif
            <span class="chip">{{ $statusLabel }}</span>
        </div>
    </div>

    <div style="display:grid;gap:.4rem;margin-top:1rem;font-size:.92rem;">
        <div style="display:flex;justify-content:space-between;gap:1rem;"><span>Payment status</span><strong>{{ $statusLabel }}</strong></div>
        <div style="display:flex;justify-content:space-between;gap:1rem;"><span>Payment method</span><strong>{{ $methodLabel }}</strong></div>
        @if($payment?->reference)
            <div style="display:flex;justify-content:space-between;gap:1rem;"><span>Transaction reference</span><strong>{{ $payment->reference }}</strong></div>
        @endif
        @if($context === 'admin' && $payment?->provider_reference)
            <div style="display:flex;justify-content:space-between;gap:1rem;"><span>Provider reference</span><strong>{{ $payment->provider_reference }}</strong></div>
        @endif
        <div style="display:flex;justify-content:space-between;gap:1rem;"><span>Amount</span><strong>{{ money($amount) }} {{ $currency }}</strong></div>
        @if($updatedAt)
            <div style="display:flex;justify-content:space-between;gap:1rem;"><span>Last updated</span><strong>{{ $updatedAt->format('M d, Y H:i') }}</strong></div>
        @endif
    </div>

    @if($isPaid)
        <p style="margin:1rem 0 0;color:var(--color-ink-muted);line-height:1.55;">
            Payment received successfully for this order.
        </p>
    @elseif($showComingSoonCopy)
        <h3 style="margin:1rem 0 .4rem;font-size:1.05rem;">{{ $headline }}</h3>
        <p style="margin:0;color:var(--color-ink-muted);line-height:1.55;">{{ $message }}</p>
        <p style="margin:.75rem 0 0;font-size:.9rem;color:var(--color-ink-muted);">
            Your order has been created, but online payment is currently unavailable. No payment has been charged. Payment options will become available when the payment service is activated.
        </p>
    @elseif(! empty($message))
        <p style="margin:1rem 0 0;color:var(--color-ink-muted);line-height:1.55;">{{ $message }}</p>
    @endif
</div>
