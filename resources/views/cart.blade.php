@extends('layouts.app')

@section('content')

<h1>🛒 Your Cart</h1>

<div style="background:white;padding:20px;border-radius:10px;margin-top:20px;">

@if(count($cart) > 0)

    @php $total = 0; @endphp

    @foreach($cart as $id => $item)

        <div style="border-bottom:1px solid #ddd;padding:10px;">

            <h3>{{ $item['name'] }}</h3>

            <p>Price: TSh {{ $item['price'] }}</p>

            <div style="display:flex;gap:10px;align-items:center;">

                <a href="/cart/decrease/{{ $id }}">➖</a>

                <b>{{ $item['quantity'] }}</b>

                <a href="/cart/increase/{{ $id }}">➕</a>

                <a href="/cart/remove/{{ $id }}">
                    <button style="background:red;color:white;padding:5px;border:none;border-radius:5px;">
                        🗑 Remove
                    </button>
                </a>

            </div>

            @php
                $total += $item['price'] * $item['quantity'];
            @endphp

        </div>

    @endforeach

    <hr>

    <h3>Total: TSh {{ $total }}</h3>

    <button style="background:green;color:white;padding:10px;border:none;border-radius:5px;">
        Proceed to Checkout
    </button>

@else

    <p>Your cart is empty 🛒</p>

@endif

</div>

@endsection