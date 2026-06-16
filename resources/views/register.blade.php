@extends('layouts.app')

@section('content')

<div style="max-width:400px;margin:auto;background:white;padding:20px;border-radius:10px;">

    <h2>Register</h2>

    <form method="POST" action="/register">
        @csrf

        <input type="text" name="name" placeholder="Full Name"
            style="width:100%;padding:10px;margin-bottom:10px;" required>

        <input type="email" name="email" placeholder="Email"
            style="width:100%;padding:10px;margin-bottom:10px;" required>

        <input type="password" name="password" placeholder="Password"
            style="width:100%;padding:10px;margin-bottom:10px;" required>

        <input type="password" name="password_confirmation" placeholder="Confirm Password"
            style="width:100%;padding:10px;margin-bottom:10px;" required>

        <button type="submit"
            style="width:100%;padding:10px;background:green;color:white;border:none;">
            Register
        </button>

    </form>
    <p>
Already have an account?

<a href="/login">
Login
</a>

</p>

    @if(session('error'))
        <p style="color:red;">{{ session('error') }}</p>
    @endif

</div>

@endsection