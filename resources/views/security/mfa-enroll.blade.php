@extends('layouts.app')
@section('title', 'Enable MFA')
@section('content')
<div class="panel" style="max-width:560px;margin:2rem auto;">
    <h1 class="font-display">Enable authenticator MFA</h1>
    <p style="color:var(--color-ink-muted);">Scan this setup key with your authenticator app, then confirm with a code. Recovery codes are shown once.</p>
    <p style="margin-top:1rem;"><strong>Secret</strong> (manual entry): <code>{{ $secret }}</code></p>
    <p style="font-size:.85rem;word-break:break-all;color:var(--color-ink-muted);">{{ $otpauthUri }}</p>
    <div style="margin:1rem 0;padding:1rem;border:1px dashed var(--color-border);">
        <strong>Recovery codes</strong>
        <ul>
            @foreach($recoveryCodes as $code)
                <li><code>{{ $code }}</code></li>
            @endforeach
        </ul>
        <p style="font-size:.85rem;color:var(--color-ink-muted);">Store these offline. They will not be shown again.</p>
    </div>
    <form method="POST" action="{{ route('security.mfa.enroll.confirm') }}" style="display:grid;gap:.75rem;">
        @csrf
        <input class="form-control" type="text" name="code" required placeholder="Confirm with 6-digit code">
        @error('code')<div style="color:#b91c1c;">{{ $message }}</div>@enderror
        <button class="btn btn-primary" type="submit">Confirm MFA</button>
    </form>
</div>
@endsection
