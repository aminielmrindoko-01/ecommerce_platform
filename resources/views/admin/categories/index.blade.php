@extends('layouts.app')
@section('title', 'Categories')
@section('content')
@include('admin._nav')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Categories</h1>
        <p>Hierarchical catalog structure</p>
    </div>
    @can('create', App\Models\Category::class)
        <a class="btn btn-primary" href="{{ route('admin.categories.create') }}">Add category</a>
    @endcan
</div>

@if(session('error'))
<div class="panel" style="border-color:#b91c1c;margin-bottom:1rem;">{{ session('error') }}</div>
@endif

<div class="panel">
@forelse($roots as $root)
    <div style="padding:.75rem 0;border-bottom:1px solid var(--color-border);">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;">
            <div>
                <strong>{{ $root->name }}</strong>
                @unless($root->is_active)<span class="badge">inactive</span>@endunless
                <span style="color:var(--color-ink-muted);">· {{ $root->products_count }} products · /{{ $root->slug }}</span>
            </div>
            <div style="display:flex;gap:.35rem;">
                @can('update', $root)
                    <a class="btn btn-ghost" href="{{ route('admin.categories.edit', $root) }}" style="padding:.35rem .65rem;">Edit</a>
                @endcan
                @can('delete', $root)
                    <form method="POST" action="{{ route('admin.categories.destroy', $root) }}" onsubmit="return confirm('Delete category?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger" type="submit" style="padding:.35rem .65rem;">Delete</button>
                    </form>
                @endcan
            </div>
        </div>
        @foreach($root->children as $child)
            <div style="padding:.5rem 0 .5rem 1.25rem;display:flex;justify-content:space-between;align-items:center;">
                <div>↳ {{ $child->name }} <span style="color:var(--color-ink-muted);">· {{ $child->products()->count() }} products</span></div>
                <div style="display:flex;gap:.35rem;">
                    @can('update', $child)
                        <a class="btn btn-ghost" href="{{ route('admin.categories.edit', $child) }}" style="padding:.35rem .65rem;">Edit</a>
                    @endcan
                </div>
            </div>
            @foreach($child->children as $grand)
                <div style="padding:.35rem 0 .35rem 2.5rem;color:var(--color-ink-muted);">↳ {{ $grand->name }}</div>
            @endforeach
        @endforeach
    </div>
@empty
    <p>No categories yet.</p>
@endforelse
</div>
@endsection
