@extends('layouts.app')

@section('title', mt('checkout.title'))

@section('content')
{{-- Checkout title with active ship-to / tax context --}}
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">{{ mt('checkout.title') }}</h1>
        <p>Ship to {{ ($mpCountries[$mpCountry ?? 'TZ']['name'] ?? 'Tanzania') }} · {{ $shippingRegion }} · Tax {{ number_format($taxRate * 100, 0) }}%</p>
    </div>
</div>

<form method="POST" action="{{ route('checkout.place') }}" style="display:grid;grid-template-columns:1.4fr .9fr;gap:1.25rem;align-items:start;">
    @csrf
    <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
    <div style="display:grid;gap:1rem;">
        {{-- Delivery address (saved chips prefill via inline script) --}}
        <section class="panel">
            <h2 style="margin-top:0;font-size:1.15rem;">Delivery address</h2>
            @if($addresses->isNotEmpty())
                <div class="chip-row" style="margin-bottom:1rem;">
                    @foreach($addresses as $address)
                        <button type="button" class="chip address-chip" data-payload='@json($address)'>{{ $address->label }} — {{ $address->city }}</button>
                    @endforeach
                </div>
            @endif
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input class="form-control" id="full_name" name="full_name" value="{{ old('full_name', auth()->user()->name) }}" required autocomplete="name">
            </div>
            <div class="form-group">
                <label for="phone">Phone (intl)</label>
                <div style="display:flex;gap:.5rem;">
                    <input class="form-control" style="max-width:90px;" value="{{ $phonePrefix }}" readonly tabindex="-1" aria-label="Country dial code">
                    <input class="form-control" id="phone" name="phone" value="{{ old('phone', auth()->user()->phone) }}" required autocomplete="tel" placeholder="7XX XXX XXX">
                </div>
            </div>
            <div class="form-group">
                <label for="line1">Address line 1</label>
                <input class="form-control" id="line1" name="line1" value="{{ old('line1') }}" required autocomplete="address-line1">
            </div>
            <div class="form-group">
                <label for="line2">Address line 2</label>
                <input class="form-control" id="line2" name="line2" value="{{ old('line2') }}" autocomplete="address-line2">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group">
                    <label for="city">City</label>
                    <input class="form-control" id="city" name="city" value="{{ old('city') }}" required autocomplete="address-level2">
                </div>
                <div class="form-group">
                    <label for="region">Region / State</label>
                    <input class="form-control" id="region" name="region" value="{{ old('region') }}" autocomplete="address-level1">
                </div>
            </div>
            <label style="display:flex;gap:.5rem;align-items:center;">
                <input type="checkbox" name="save_address" value="1"> Save this address to my account
            </label>
        </section>

        {{-- Shipping method radios --}}
        <section class="panel">
            <h2 style="margin-top:0;font-size:1.15rem;">Delivery option</h2>
            @foreach($shippingOptions as $key => $option)
                <label style="display:flex;justify-content:space-between;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--color-border);cursor:pointer;">
                    <span style="display:flex;gap:.6rem;align-items:center;">
                        <input type="radio" name="shipping_method" value="{{ $key }}" @checked($key === 'standard') required>
                        {{ $option['label'] }}
                    </span>
                    <strong>{{ $option['cost'] == 0 ? 'FREE' : money($option['cost']) }}</strong>
                </label>
            @endforeach
        </section>

        {{-- Payment method radios (live charging disabled — coming soon / offline) --}}
        <section class="panel">
            <h2 style="margin-top:0;font-size:1.15rem;">Payment method</h2>
            <div class="panel" style="margin:0 0 1rem;background:var(--color-surface);border:1px dashed var(--color-border);">
                <strong>Online Payment — Coming Soon</strong>
                <p style="margin:.4rem 0 0;color:var(--color-ink-muted);font-size:.9rem;line-height:1.5;">
                    Online payment is currently unavailable. You can place your order now, and payment will be enabled when the service becomes available. Placing an order does <strong>not</strong> charge your account and does <strong>not</strong> mark the order as paid.
                </p>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.55rem;">
                @foreach($paymentMethods as $key => $method)
                    <label class="payment-option" style="display:flex;flex-direction:column;gap:.35rem;align-items:flex-start;">
                        <span style="display:flex;gap:.55rem;align-items:center;width:100%;">
                            <input type="radio" name="payment_method" value="{{ $key }}" @checked(old('payment_method', 'mpesa') === $key) required>
                            <span style="flex:1;">{{ $method['label'] }}</span>
                            <span class="chip" style="font-size:.72rem;">{{ $method['badge'] }}</span>
                        </span>
                        @if($method['coming_soon'])
                            <span style="font-size:.78rem;color:var(--color-ink-muted);padding-left:1.6rem;">Online Payment · Coming Soon</span>
                        @elseif($method['offline'])
                            <span style="font-size:.78rem;color:var(--color-ink-muted);padding-left:1.6rem;">Offline · stays pending until verified</span>
                        @endif
                    </label>
                @endforeach
            </div>
            <div style="margin-top:1rem;">
                <x-payment-methods />
            </div>
        </section>
    </div>

    {{-- Sticky order summary --}}
    <aside class="panel glass-panel" style="position:sticky;top:5.5rem;">
        <h2 style="margin-top:0;font-size:1.15rem;">Order summary</h2>
        @foreach($cart as $item)
            <div style="display:flex;justify-content:space-between;gap:.75rem;padding:.45rem 0;border-bottom:1px solid var(--color-border);font-size:.92rem;">
                <span>{{ $item['name'] }} × {{ $item['quantity'] }}</span>
                <strong>{{ money($item['price'] * $item['quantity']) }}</strong>
            </div>
        @endforeach
        <div style="display:grid;gap:.45rem;margin-top:.85rem;font-size:.95rem;">
            <div style="display:flex;justify-content:space-between;"><span>Subtotal</span><span>{{ money($subtotal) }}</span></div>
            <div style="display:flex;justify-content:space-between;"><span>Discount</span><span>- {{ money($discount) }}</span></div>
            <div style="display:flex;justify-content:space-between;"><span>Est. shipping</span><span>{{ $shipping == 0 ? 'FREE' : money($shipping) }}</span></div>
            <div style="display:flex;justify-content:space-between;"><span>Tax ({{ number_format($taxRate * 100, 0) }}%)</span><span>{{ money($tax) }}</span></div>
            <div style="display:flex;justify-content:space-between;font-size:1.15rem;padding-top:.5rem;border-top:1px solid var(--color-border);"><strong>Total</strong><strong>{{ money($total) }}</strong></div>
        </div>
        <button class="btn btn-accent" type="submit" style="width:100%;margin-top:1rem;">Place order</button>
        <p style="font-size:.8rem;color:var(--color-ink-muted);margin:.65rem 0 0;">By placing your order you agree to SANA buyer terms.</p>
    </aside>
</form>

<style>
@media (max-width: 900px) {
    form[style*="1.4fr"] { grid-template-columns: 1fr !important; }
}
</style>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.address-chip').forEach((chip) => {
    chip.addEventListener('click', () => {
        const data = JSON.parse(chip.dataset.payload);
        ['full_name','phone','line1','line2','city','region'].forEach((key) => {
            const el = document.getElementById(key);
            if (el && data[key] != null) el.value = data[key];
        });
        document.querySelectorAll('.address-chip').forEach((c) => c.classList.remove('is-active'));
        chip.classList.add('is-active');
    });
});
</script>
@endpush
