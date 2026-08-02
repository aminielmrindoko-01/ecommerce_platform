{{-- Customer-facing payment availability / coming-soon panel (no secrets) --}}
@php
    /** @var array<string, mixed> $paymentInit */
    $status = $paymentInit['status'] ?? 'coming_soon';
    $methodLabel = $paymentInit['method_label'] ?? 'Selected method';
    $headline = $paymentInit['headline'] ?? 'Payment Service Coming Soon';
    $message = $paymentInit['message'] ?? "We're preparing secure online payments for this service.";
    $isComingSoon = in_array($status, ['coming_soon', 'unavailable'], true);
    $paymentStatus = $order->payment_status ?? 'pending';
@endphp

<div class="panel" style="text-align:left;margin:1.25rem 0;border:1px solid var(--color-border);">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
        <div>
            <div style="font-size:.8rem;color:var(--color-ink-muted);text-transform:uppercase;letter-spacing:.04em;">Payment method</div>
            <strong style="font-size:1.1rem;">{{ $methodLabel }}</strong>
        </div>
        <div style="text-align:right;">
            @if($isComingSoon)
                <span class="chip" style="background:var(--color-surface);">Coming soon</span>
            @elseif($paymentStatus === 'paid')
                <span class="chip">Payment paid</span>
            @else
                <span class="chip">Payment {{ ucfirst($paymentStatus) }}</span>
            @endif
        </div>
    </div>

    @if($isComingSoon)
        <h3 style="margin:1rem 0 .4rem;font-size:1.05rem;">{{ $headline }}</h3>
        <p style="margin:0;color:var(--color-ink-muted);line-height:1.55;">{{ $message }}</p>
        <p style="margin:.75rem 0 0;font-size:.9rem;color:var(--color-ink-muted);">
            Your order is saved. Online payment for <strong>{{ $methodLabel }}</strong> is not active yet — payment remains <strong>pending</strong> until a genuine verified payment is recorded.
        </p>
    @else
        <p style="margin:1rem 0 0;color:var(--color-ink-muted);line-height:1.55;">{{ $message }}</p>
    @endif
</div>
