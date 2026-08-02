@extends('layouts.app')
@section('title', 'Register')
@section('content')
<div class="panel" style="max-width:440px;margin:1rem auto;">
    <h1 class="font-display" style="margin-top:0;">Join SANA Market</h1>
    <p style="color:var(--color-ink-muted);">Create a buyer account in under a minute.</p>
    <form method="POST" action="{{ route('register.submit') }}">
        @csrf
        <div class="form-group">
            <label for="name">Full name</label>
            <input class="form-control" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required>
            @error('email')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input class="form-control" id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
            @error('password')<div class="form-error">{{ $message }}</div>@enderror
            <label for="password_confirmation" style="margin-top:.75rem;">Confirm password</label>
            <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>
        <button class="btn btn-accent" type="submit" style="width:100%;">Create account</button>
    </form>
    <p style="margin-top:1rem;font-size:.92rem;">Already have an account? <a href="{{ route('login') }}" style="color:var(--color-brand);font-weight:700;">Login</a></p>
</div>
@endsection
