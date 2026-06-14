@extends('layouts.app')

@section('content')

<div style="display:flex;gap:30px;background:white;padding:20px;border-radius:10px;">

    <!-- IMAGE -->
    <div style="flex:1;">
<img src="/images/products/phone.jpg"
     style="width:100%;height:300px;object-fit:cover;border-radius:10px;">    </div>

    <!-- DETAILS -->
    <div style="flex:1;">

        <h1>{{ $product->name }}</h1>

        <h2 style="color:green;">
            TSh {{ $product->price }}
        </h2>

        <p><b>Stock:</b> {{ $product->stock }}</p>

        <p><b>Store:</b> {{ $product->vendor->store_name ?? 'Unknown Store' }}</p>

        <p style="margin-top:15px;color:#555;">
            {{ $product->description }}
        </p>

        <button style="margin-top:20px;background:orange;color:white;padding:10px 15px;border:none;border-radius:5px;width:100%;">
            🛒 Add to Cart
        </button>

    </div>

</div>

@endsection