@extends('layouts.app')

@section('content')

<div style="max-width:420px;margin:auto;background:white;padding:24px;border-radius:14px;box-shadow:0 18px 60px rgba(0,0,0,0.08);">
    <h2 style="margin-top:0;">Register</h2>

    <form method="POST" action="{{ route('register.submit') }}">
        @csrf

        <input type="text" name="name" value="{{ old('name') }}" placeholder="Full Name" style="width:100%;padding:12px;margin-bottom:12px;border:1px solid #d1d5db;border-radius:10px;" required>
        @error('name')
            <div style="color:#dc2626;margin-bottom:12px;">{{ $message }}</div>
        @enderror

        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" style="width:100%;padding:12px;margin-bottom:12px;border:1px solid #d1d5db;border-radius:10px;" required>
        @error('email')
            <div style="color:#dc2626;margin-bottom:12px;">{{ $message }}</div>
        @enderror

        <input type="password" name="password" placeholder="Password" style="width:100%;padding:12px;margin-bottom:12px;border:1px solid #d1d5db;border-radius:10px;" required>
        @error('password')
            <div style="color:#dc2626;margin-bottom:12px;">{{ $message }}</div>
        @enderror

        <input type="password" name="password_confirmation" placeholder="Confirm Password" style="width:100%;padding:12px;margin-bottom:16px;border:1px solid #d1d5db;border-radius:10px;" required>

        <button type="submit" style="width:100%;padding:12px;background:#10b981;color:white;border:none;border-radius:10px;cursor:pointer;">Register</button>
    </form>

    <p style="margin-top:18px;">Already have an account? <a href="{{ route('login') }}">Login</a></p>
</div>

@endsection