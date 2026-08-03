@extends('layouts.app')
@section('title', isset($category) ? 'Edit category' : 'Create category')
@section('content')
@include('admin._nav')
@php $editing = isset($category); @endphp
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">{{ $editing ? 'Edit category' : 'Create category' }}</h1>
    </div>
    <a class="btn btn-ghost" href="{{ route('admin.categories.index') }}">Back</a>
</div>

@if($errors->any())
<div class="panel" style="border-color:#b91c1c;margin-bottom:1rem;">
    <ul style="margin:0;padding-left:1.2rem;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="panel" style="display:grid;gap:1rem;max-width:640px;">
    @csrf
    @if($editing) @method('PUT') @endif
    <div>
        <label class="form-label">Name</label>
        <input class="form-control" name="name" value="{{ old('name', $category->name ?? '') }}" required>
    </div>
    <div>
        <label class="form-label">Slug</label>
        <input class="form-control" name="slug" value="{{ old('slug', $category->slug ?? '') }}" placeholder="Auto-generated if empty">
    </div>
    <div>
        <label class="form-label">Parent</label>
        <select class="form-control" name="parent_id">
            <option value="">— Root —</option>
            @foreach($parents as $parent)
                <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id ?? '') == $parent->id)>{{ $parent->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Sort order</label>
        <input class="form-control" type="number" min="0" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
    </div>
    <div>
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="3">{{ old('description', $category->description ?? '') }}</textarea>
    </div>
    <label style="display:flex;gap:.5rem;align-items:center;">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))>
        Active
    </label>
    <button class="btn btn-primary" type="submit">{{ $editing ? 'Save' : 'Create' }}</button>
</form>

@if($editing && auth()->user()->can('delete', $category))
<form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="panel" style="margin-top:1rem;max-width:640px;" onsubmit="return confirm('Delete this category?')">
    @csrf @method('DELETE')
    <h2 style="margin-top:0;font-size:1.05rem;">Delete category</h2>
    <p style="color:var(--color-ink-muted);">If products exist, choose a reassignment category.</p>
    <select class="form-control" name="reassign_to" style="margin-bottom:.75rem;">
        <option value="">— None —</option>
        @foreach($parents as $parent)
            <option value="{{ $parent->id }}">{{ $parent->name }}</option>
        @endforeach
    </select>
    <button class="btn btn-danger" type="submit">Delete</button>
</form>
@endif
@endsection
