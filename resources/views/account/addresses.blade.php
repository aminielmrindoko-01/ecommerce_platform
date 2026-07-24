@extends('layouts.app')
@section('title', 'Addresses')
@section('content')
@include('account._nav')
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;align-items:start;">
    <div>
        <h1 class="font-display">Saved addresses</h1>
        @forelse($addresses as $address)
            <article class="panel" style="margin-bottom:.75rem;">
                <strong>{{ $address->label }}</strong> @if($address->is_default)<span class="badge badge-new">Default</span>@endif
                <p style="margin:.4rem 0;line-height:1.55;color:var(--color-ink-muted);">
                    {{ $address->full_name }} · {{ $address->phone }}<br>
                    {{ $address->line1 }} {{ $address->line2 }}<br>
                    {{ $address->city }}, {{ $address->region }} {{ $address->postal_code }}
                </p>
                <form method="POST" action="{{ route('account.addresses.destroy', $address) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" type="submit" style="padding:.4rem .75rem;">Remove</button>
                </form>
            </article>
        @empty
            <p class="panel">No addresses yet.</p>
        @endforelse
    </div>
    <form method="POST" action="{{ route('account.addresses.store') }}" class="panel">
        @csrf
        <h2 style="margin-top:0;font-size:1.1rem;">Add address</h2>
        <div class="form-group"><label>Label</label><input class="form-control" name="label" value="Home" required></div>
        <div class="form-group"><label>Full name</label><input class="form-control" name="full_name" value="{{ auth()->user()->name }}" required></div>
        <div class="form-group"><label>Phone</label><input class="form-control" name="phone" required></div>
        <div class="form-group"><label>Line 1</label><input class="form-control" name="line1" required></div>
        <div class="form-group"><label>Line 2</label><input class="form-control" name="line2"></div>
        <div class="form-group"><label>City</label><input class="form-control" name="city" required></div>
        <div class="form-group"><label>Region</label><input class="form-control" name="region"></div>
        <div class="form-group"><label>Postal code</label><input class="form-control" name="postal_code"></div>
        <label style="display:flex;gap:.4rem;align-items:center;margin-bottom:1rem;"><input type="checkbox" name="is_default" value="1"> Set as default</label>
        <button class="btn btn-primary" type="submit">Save address</button>
    </form>
</div>
<style>@media(max-width:900px){.site-main>div[style*="1fr 1fr"]{grid-template-columns:1fr!important;}}</style>
@endsection
