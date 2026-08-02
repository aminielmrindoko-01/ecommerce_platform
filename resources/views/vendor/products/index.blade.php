@extends('layouts.app')
@section('title', 'Vendor products')
@section('content')
@include('vendor._nav')

<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Your products</h1>
        <p>{{ $vendor->store_name }} catalog</p>
    </div>
    <a class="btn btn-primary" href="{{ route('vendor.products.create') }}">Add product</a>
</div>

<div class="panel section">
    <table class="table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th align="left">Product</th>
                <th align="left">Price</th>
                <th align="left">Stock</th>
                <th align="left">Category</th>
                <th align="right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr style="border-top:1px solid var(--color-border);">
                    <td style="padding:.75rem 0;">
                        <strong>{{ $product->name }}</strong>
                        <div style="color:var(--color-ink-muted);font-size:.85rem;">{{ $product->brand }}</div>
                    </td>
                    <td>TSh {{ number_format($product->price, 0) }}</td>
                    <td>{{ $product->stock }}</td>
                    <td>{{ $product->category->name ?? '—' }}</td>
                    <td align="right">
                        <a class="btn btn-ghost" href="{{ route('vendor.products.edit', $product) }}" style="padding:.35rem .75rem;">Edit</a>
                        <form action="{{ route('vendor.products.destroy', $product) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger" type="submit" style="padding:.35rem .75rem;">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:1rem 0;color:var(--color-ink-muted);">No products yet. Create your first listing.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem;">{{ $products->links() }}</div>
</div>
@endsection
