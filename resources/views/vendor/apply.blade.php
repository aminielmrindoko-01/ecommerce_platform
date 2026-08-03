@extends('layouts.app')
@section('title', 'Become a vendor')
@section('content')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Vendor application</h1>
        <p>Apply to sell on SANA Market. Applications require admin approval.</p>
    </div>
</div>
@if($errors->any())
<div class="panel" style="border-color:#b91c1c;margin-bottom:1rem;">
    <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif
<form method="POST" action="{{ route('vendor.apply.store') }}" class="panel" style="display:grid;gap:1rem;max-width:640px;">
    @csrf
    <div>
        <label class="form-label">Store name</label>
        <input class="form-control" name="store_name" value="{{ old('store_name') }}" required>
    </div>
    <div>
        <label class="form-label">Business email</label>
        <input class="form-control" type="email" name="email" value="{{ old('email', auth()->user()->email) }}">
    </div>
    <div>
        <label class="form-label">Location</label>
        <input class="form-control" name="location" value="{{ old('location') }}">
    </div>
    <div>
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="4">{{ old('description') }}</textarea>
    </div>
    <div>
        <label class="form-label">Notes for reviewers</label>
        <textarea class="form-control" name="application_notes" rows="3">{{ old('application_notes') }}</textarea>
    </div>
    <button class="btn btn-primary" type="submit">Submit application</button>
</form>
@endsection
