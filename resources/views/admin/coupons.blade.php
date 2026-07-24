@extends('layouts.app')
@section('title', 'Coupons')
@section('content')
@include('admin._nav')
<h1 class="font-display">Coupons</h1>
<div class="panel" style="margin-top:1rem;padding:0;">
@foreach($coupons as $coupon)
<div style="display:flex;justify-content:space-between;padding:1rem;border-bottom:1px solid var(--color-border);flex-wrap:wrap;gap:.5rem;">
<div>
<strong>{{ $coupon->code }}</strong>
<div style="color:var(--color-ink-muted);font-size:.9rem;">{{ $coupon->type === 'percent' ? $coupon->value.'%' : 'TSh '.number_format($coupon->value,0) }} off · min TSh {{ number_format($coupon->min_order,0) }}</div>
</div>
<span class="badge {{ $coupon->is_active ? 'badge-stock' : 'badge-sale' }}">{{ $coupon->is_active ? 'Active' : 'Inactive' }}</span>
</div>
@endforeach
</div>
@endsection
