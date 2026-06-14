@extends('layouts.app')

@section('content')

<div style="max-width:400px;margin:auto;background:white;padding:20px;border-radius:10px;">

    <h2>Login</h2>

    <form method="POST" action="/login">
        @csrf

        <input type="email" name="email" placeholder="Email"
            style="width:100%;padding:10px;margin-bottom:10px;" required>

        <input type="password" name="password" placeholder="Password"
            style="width:100%;padding:10px;margin-bottom:10px;" required>

        <button type="submit"
            style="width:100%;padding:10px;background:orange;color:white;border:none;">
            Login
        </button>

    </form>

    @if(session('error'))
        <p style="color:red;">{{ session('error') }}</p>
    @endif

</div>

@endsection