@extends('layouts.app')
@section('title', 'Authenticator challenge')
@section('content')
<div class="panel" style="max-width:420px;margin:2rem auto;">
    <h1 class="font-display">Verify it's you</h1>
    <p style="color:var(--color-ink-muted);">Enter the 6-digit code from your authenticator app (or a recovery code).</p>
    <form method="POST" action="{{ route('security.mfa.challenge.submit') }}" style="display:grid;gap:.75rem;margin-top:1rem;">
        @csrf
        <input class="form-control" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required placeholder="123456">
        @error('code')<div style="color:#b91c1c;">{{ $message }}</div>@enderror
        <button class="btn btn-primary" type="submit">Continue</button>
    </form>
</div>
@endsection
