@extends('layouts.app')
@section('title', 'Store profile')
@section('content')
@include('vendor._nav')

<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Store profile</h1>
        <p>Public store details for {{ $vendor->store_name }}</p>
    </div>
</div>

<form method="POST" action="{{ route('vendor.profile.update') }}" class="panel section" style="max-width:640px;">
    @csrf @method('PUT')
    <div class="form-group">
        <label for="store_name">Store name</label>
        <input class="form-control" id="store_name" name="store_name" value="{{ old('store_name', $vendor->store_name) }}" required>
        @error('store_name')<div class="form-error">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label for="email">Store email</label>
        <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $vendor->email) }}">
        @error('email')<div class="form-error">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label for="location">Location</label>
        <input class="form-control" id="location" name="location" value="{{ old('location', $vendor->location) }}">
    </div>
    <div class="form-group">
        <label for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $vendor->description) }}</textarea>
    </div>
    <p style="color:var(--color-ink-muted);font-size:.9rem;">Verification status is managed by admins{{ $vendor->is_verified ? ' — your store is verified.' : '.' }}</p>
    <button class="btn btn-primary" type="submit">Save profile</button>
</form>
@endsection
