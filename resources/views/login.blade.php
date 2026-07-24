@extends('layouts.app')
@section('title', 'Login')
@section('content')
{{-- Login form (throttled POST via login.submit) --}}
<div class="panel" style="max-width:440px;margin:1rem auto;">
    <h1 class="font-display" style="margin-top:0;">Welcome back</h1>
    <p style="color:var(--color-ink-muted);">Sign in to checkout, track orders, and manage your wishlist.</p>
    <form method="POST" action="{{ route('login.submit') }}">
        @csrf
        <div class="form-group">
            <label for="email">Email</label>
            <input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
            @error('email')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input class="form-control" id="password" type="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%;">Login</button>
    </form>
    <p style="margin-top:1rem;font-size:.92rem;">New here? <a href="{{ route('register') }}" style="color:var(--color-brand);font-weight:700;">Create an account</a></p>
    <p style="margin-top:.75rem;font-size:.8rem;color:var(--color-ink-muted);">Demo: admin@example.com / password · test@example.com / password</p>
</div>
@endsection
