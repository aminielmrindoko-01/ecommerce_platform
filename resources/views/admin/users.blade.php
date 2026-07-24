@extends('layouts.app')
@section('title', 'Admin users')
@section('content')
@include('admin._nav')
<h1 class="font-display">Customers & roles</h1>
<div class="panel" style="margin-top:1rem;overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:640px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);"><th style="padding:.75rem;">Name</th><th>Email</th><th>Role</th><th>Update</th></tr></thead>
<tbody>
@foreach($users as $user)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $user->name }}</td>
<td>{{ $user->email }}</td>
<td>{{ $user->role }}</td>
<td style="padding:.75rem;">
<form method="POST" action="{{ route('admin.users.update', $user->id) }}" style="display:flex;gap:.4rem;">
@csrf @method('PUT')
<select name="role" class="form-control" style="width:auto;">
@foreach(['customer','vendor','admin'] as $role)
<option value="{{ $role }}" @selected($user->role === $role)>{{ $role }}</option>
@endforeach
</select>
<button class="btn btn-primary" type="submit" style="padding:.45rem .7rem;">Save</button>
</form>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $users->links() }}</div>
@endsection
