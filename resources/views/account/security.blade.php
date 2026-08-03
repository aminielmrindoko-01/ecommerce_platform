@extends('layouts.app')
@section('title', 'Security')
@section('content')
@include('account._nav')
@php $user = auth()->user(); @endphp

<form method="POST" action="{{ route('account.password.update') }}" class="panel" style="max-width:520px;">
    @csrf @method('PUT')
    <h1 class="font-display" style="margin-top:0;">Password & security</h1>
    <div class="form-group"><label>Current password</label><input class="form-control" type="password" name="current_password" required></div>
    <div class="form-group"><label>New password</label><input class="form-control" type="password" name="password" required></div>
    <div class="form-group"><label>Confirm password</label><input class="form-control" type="password" name="password_confirmation" required></div>
    <button class="btn btn-primary" type="submit">Update password</button>
</form>

<div class="panel" style="max-width:520px;margin-top:1.25rem;">
    <h2 style="margin-top:0;">Multi-factor authentication</h2>
    @if($user->hasMfaEnabled())
        <p style="color:var(--color-ink-muted);">MFA is enabled for this account.</p>
        <form method="POST" action="{{ route('security.mfa.disable') }}" style="display:grid;gap:.75rem;">
            @csrf
            <input class="form-control" type="password" name="password" required placeholder="Confirm password (step-up)">
            <input class="form-control" type="text" name="code" required placeholder="Authenticator code">
            <button class="btn" type="submit" onclick="return confirm('Disable MFA?')">Disable MFA</button>
        </form>
    @elseif($user->requiresMfaEnrollment())
        <p style="color:var(--color-ink-muted);">This privileged account should enable MFA.</p>
        <a class="btn btn-primary" href="{{ route('security.mfa.enroll') }}">Enable authenticator MFA</a>
    @else
        <p style="color:var(--color-ink-muted);">MFA is optional for your current roles.</p>
        @if($user->hasPermission('admin.access'))
            <a class="btn" href="{{ route('security.mfa.enroll') }}">Enable MFA</a>
        @endif
    @endif
</div>
@endsection
