<!DOCTYPE html>
<html>
<head>
    <title>Products</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .card { background: white; padding: 15px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .price { color: green; font-weight: bold; }
    </style>
</head>
<body>

<h1>🛒 Marketplace Products</h1>

<div class="grid">

@foreach($products as $product)
    <div class="card">
        <h3>{{ $product->name }}</h3>

        <p class="price">TSh {{ $product->price }}</p>

        <p>Stock: {{ $product->stock }}</p>

        <p>Store: {{ $product->vendor->store_name ?? 'Unknown' }}</p>

        <p>{{ $product->description }}</p>
    </div>
@endforeach

</div>

</body>
</html>