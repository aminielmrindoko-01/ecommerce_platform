@extends('layouts.app')
@section('title', 'Security')
@section('content')
@include('account._nav')
<form method="POST" action="{{ route('account.password.update') }}" class="panel" style="max-width:520px;">
    @csrf @method('PUT')
    <h1 class="font-display" style="margin-top:0;">Password & security</h1>
    <div class="form-group"><label>Current password</label><input class="form-control" type="password" name="current_password" required></div>
    <div class="form-group"><label>New password</label><input class="form-control" type="password" name="password" required></div>
    <div class="form-group"><label>Confirm password</label><input class="form-control" type="password" name="password_confirmation" required></div>
    <button class="btn btn-primary" type="submit">Update password</button>
</form>
@endsection
