@extends('layouts.app')

@section('content')

<div style="max-width:420px;margin:auto;background:white;padding:24px;border-radius:14px;box-shadow:0 18px 60px rgba(0,0,0,0.08);">
    <h2 style="margin-top:0;">Login</h2>

    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px;background:#d1fae5;color:#065f46;border-radius:10px;">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('login.submit') }}">
        @csrf

        <input type="email" name="email" placeholder="Email" value="{{ old('email') }}" style="width:100%;padding:12px;margin-bottom:12px;border:1px solid #d1d5db;border-radius:10px;" required>
        @error('email')
            <div style="color:#dc2626;margin-bottom:12px;">{{ $message }}</div>
        @enderror

        <input type="password" name="password" placeholder="Password" style="width:100%;padding:12px;margin-bottom:12px;border:1px solid #d1d5db;border-radius:10px;" required>
        @error('password')
            <div style="color:#dc2626;margin-bottom:12px;">{{ $message }}</div>
        @enderror

        <button type="submit" style="width:100%;padding:12px;background:#f97316;color:white;border:none;border-radius:10px;cursor:pointer;">Login</button>
    </form>

    <p style="margin-top:18px;">Don't have an account? <a href="{{ route('register') }}">Register</a></p>
</div>

@endsection