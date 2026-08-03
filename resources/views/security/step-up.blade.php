@extends('layouts.app')
@section('title', 'Confirm identity')
@section('content')
<div class="panel" style="max-width:420px;margin:2rem auto;">
    <h1 class="font-display">Confirm your password</h1>
    <p style="color:var(--color-ink-muted);">This action requires recent authentication (step-up).</p>
    <form method="POST" action="{{ route('security.step-up.confirm') }}" style="display:grid;gap:.75rem;margin-top:1rem;">
        @csrf
        <input type="hidden" name="intended" value="{{ $intended }}">
        <input class="form-control" type="password" name="password" required autocomplete="current-password" placeholder="Password">
        @error('password')<div style="color:#b91c1c;">{{ $message }}</div>@enderror
        <button class="btn btn-primary" type="submit">Confirm</button>
    </form>
</div>
@endsection
