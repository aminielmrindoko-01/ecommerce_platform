@extends('layouts.app')
@section('title', 'Flash deals')
@section('content')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Flash deals</h1>
        <p>Limited-time marketplace offers</p>
    </div>
    <div class="flash-timer" data-countdown="{{ $flashEndsAt }}"></div>
</div>
<div class="products-grid">
@forelse($deals as $product)
    <x-product-card :product="$product" />
@empty
    <p class="panel">No active flash deals right now.</p>
@endforelse
</div>
<div style="margin-top:1.25rem;">{{ $deals->links() }}</div>
@endsection
