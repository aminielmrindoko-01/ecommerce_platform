@extends('layouts.app')

@section('content')

<h1>🛒 Your Cart</h1>

<div style="background:white;padding:20px;border-radius:12px;margin-top:20px;">

@if(count($cart) > 0)

    @php $total = 0; @endphp

    @foreach($cart as $id => $item)
        <div style="border-bottom:1px solid #e5e7eb;padding:18px 0;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 style="margin:0;">{{ $item['name'] }}</h3>
                    <p style="margin:6px 0 0;color:#4b5563;">Price: TSh {{ number_format($item['price'], 2) }}</p>
                </div>

                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <form method="POST" action="{{ route('cart.decrease', $id) }}">
                        @csrf
                        <button type="submit" style="padding:8px 12px;border:none;background:#ef4444;color:white;border-radius:8px;cursor:pointer;">➖</button>
                    </form>

                    <strong>{{ $item['quantity'] }}</strong>

                    <form method="POST" action="{{ route('cart.increase', $id) }}">
                        @csrf
                        <button type="submit" style="padding:8px 12px;border:none;background:#16a34a;color:white;border-radius:8px;cursor:pointer;">➕</button>
                    </form>

                    <form method="POST" action="{{ route('cart.remove', $id) }}">
                        @csrf
                        <button type="submit" style="padding:8px 12px;border:none;background:#6b7280;color:white;border-radius:8px;cursor:pointer;">🗑 Remove</button>
                    </form>
                </div>
            </div>

            @php $total += $item['price'] * $item['quantity']; @endphp
        </div>
    @endforeach

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-top:20px;">
        <h3 style="margin:0;">Total: TSh {{ number_format($total, 2) }}</h3>
        <a href="{{ route('checkout') }}" style="background:#10b981;color:white;padding:12px 18px;border-radius:10px;text-decoration:none;">Proceed to Checkout</a>
    </div>

@else
    <p>Your cart is empty 🛒</p>
@endif

</div>

@endsection