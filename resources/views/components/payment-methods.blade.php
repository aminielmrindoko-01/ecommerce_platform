{{-- Professional payment method marks (inline SVG, brand-approximate colors for recognition) --}}
@props(['compact' => false])

@php
$methods = [
    'visa' => ['label' => 'Visa', 'bg' => '#1a1f71'],
    'mastercard' => ['label' => 'Mastercard', 'bg' => '#252525'],
    'amex' => ['label' => 'Amex', 'bg' => '#2e77bb'],
    'paypal' => ['label' => 'PayPal', 'bg' => '#003087'],
    'stripe' => ['label' => 'Stripe', 'bg' => '#635bff'],
    'apple_pay' => ['label' => 'Apple Pay', 'bg' => '#111111'],
    'google_pay' => ['label' => 'Google Pay', 'bg' => '#1f1f1f'],
    'mpesa' => ['label' => 'M-Pesa', 'bg' => '#4caf50'],
    'airtel' => ['label' => 'Airtel Money', 'bg' => '#ed1c24'],
    'tigo' => ['label' => 'Tigo Pesa', 'bg' => '#00377b'],
    'halopesa' => ['label' => 'HaloPesa', 'bg' => '#00a3e0'],
    'mixx' => ['label' => 'Mixx by Yas', 'bg' => '#6c2bd9'],
    'mtn' => ['label' => 'MTN MoMo', 'bg' => '#ffcc00'],
    'orange' => ['label' => 'Orange Money', 'bg' => '#ff7900'],
    'bank' => ['label' => 'Bank', 'bg' => '#0d7377'],
    'cod' => ['label' => 'COD', 'bg' => '#5b6b78'],
];
@endphp

<div class="pay-methods {{ $compact ? 'pay-methods--compact' : '' }}" role="list" aria-label="Accepted payment methods">
@foreach($methods as $key => $method)
    <div class="pay-method" role="listitem" title="{{ $method['label'] }}" style="--pay-bg: {{ $method['bg'] }}">
        @if($key === 'visa')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="currentColor"/><text x="24" y="18" text-anchor="middle" fill="#fff" font-size="9" font-weight="800" font-family="Arial">VISA</text></svg>
        @elseif($key === 'mastercard')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#252525"/><circle cx="20" cy="14" r="7" fill="#eb001b"/><circle cx="28" cy="14" r="7" fill="#f79e1b"/><path d="M24 8.5a7 7 0 0 1 0 11 7 7 0 0 1 0-11z" fill="#ff5f00"/></svg>
        @elseif($key === 'mpesa')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#4caf50"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="7" font-weight="800" font-family="Arial">M-PESA</text></svg>
        @elseif($key === 'airtel')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#ed1c24"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="6.5" font-weight="800" font-family="Arial">AIRTEL</text></svg>
        @elseif($key === 'tigo')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#00377b"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="7" font-weight="800" font-family="Arial">TIGO</text></svg>
        @elseif($key === 'halopesa')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#00a3e0"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="5.5" font-weight="800" font-family="Arial">HALOPESA</text></svg>
        @elseif($key === 'mixx')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#6c2bd9"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="8" font-weight="800" font-family="Arial">MIXX</text></svg>
        @elseif($key === 'mtn')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#ffcc00"/><text x="24" y="17" text-anchor="middle" fill="#1a1a1a" font-size="8" font-weight="900" font-family="Arial">MTN</text></svg>
        @elseif($key === 'orange')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#ff7900"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="6" font-weight="800" font-family="Arial">ORANGE</text></svg>
        @elseif($key === 'paypal')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#003087"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="7" font-weight="800" font-family="Arial">PayPal</text></svg>
        @elseif($key === 'apple_pay')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#111"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="6" font-weight="700" font-family="Arial"> Pay</text></svg>
        @elseif($key === 'google_pay')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#fff" stroke="#dadce0"/><text x="24" y="17" text-anchor="middle" fill="#3c4043" font-size="6" font-weight="700" font-family="Arial">G Pay</text></svg>
        @elseif($key === 'stripe')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#635bff"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="7" font-weight="800" font-family="Arial">Stripe</text></svg>
        @elseif($key === 'amex')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#2e77bb"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="7" font-weight="800" font-family="Arial">AMEX</text></svg>
        @elseif($key === 'bank')
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#0d7377"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="7" font-weight="800" font-family="Arial">BANK</text></svg>
        @else
            <svg viewBox="0 0 48 28" aria-hidden="true"><rect width="48" height="28" rx="4" fill="#5b6b78"/><text x="24" y="17" text-anchor="middle" fill="#fff" font-size="8" font-weight="800" font-family="Arial">COD</text></svg>
        @endif
        @unless($compact)
            <span class="pay-method-label">{{ $method['label'] }}</span>
        @endunless
    </div>
@endforeach
</div>
