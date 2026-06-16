<!DOCTYPE html>
<html>
<head>
    <title>Products</title>
</head>

<body>

<h1>Our Products</h1>


@foreach($products as $product)

<div>

<h3>
{{ $product->name }}
</h3>

<p>
Price: {{ $product->price }}
</p>


<a href="/product/{{ $product->id }}">
View Product
</a>

</div>

<hr>

@endforeach


</body>
</html>