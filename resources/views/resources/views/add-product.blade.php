<form method="POST" action="/products">
    @csrf

    <input type="text" name="name" placeholder="Product Name">
    <input type="number" name="price" placeholder="Price">
    <input type="number" name="stock" placeholder="Stock">
    <textarea name="description" placeholder="Description"></textarea>

    <button type="submit">Add Product</button>
</form>