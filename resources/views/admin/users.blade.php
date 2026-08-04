@extends('layouts.app')
@section('title', 'Admin users')
@section('content')
@include('admin._nav')
<h1 class="font-display">Customers & roles</h1>
<p style="color:var(--color-ink-muted);">Legacy marketplace role plus RBAC role assignment. Super Admin assignment is restricted to Super Admins.</p>
@if(session('success'))<div class="panel">{{ session('success') }}</div>@endif
@if(session('error'))<div class="panel" style="border-color:#b91c1c;">{{ session('error') }}</div>@endif
<div class="panel" style="margin-top:1rem;overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:720px;">
<thead><tr style="text-align:left;border-bottom:1px solid var(--color-border);"><th style="padding:.75rem;">Name</th><th>Email</th><th>Status</th><th>Legacy</th><th>RBAC</th><th>Update</th></tr></thead>
<tbody>
@foreach($users as $user)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $user->name }}</td>
<td>{{ $user->email }}</td>
<td>@if($user->is_active)<span class="chip">active</span>@else<span class="chip">inactive</span>@endif</td>
<td>{{ $user->role }}</td>
<td>{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
<td style="padding:.75rem;">
@if(auth()->user()?->hasPermission('users.update') || auth()->user()?->hasPermission('roles.update'))
    @if($user->id === auth()->id())
        <span style="color:var(--color-ink-muted);">Cannot change own role</span>
    @else
<form method="POST" action="{{ route('admin.users.update', $user->id) }}" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;">
@csrf @method('PUT')
<select name="role" class="form-control" style="width:auto;" aria-label="Legacy role">
@foreach(['customer','vendor','admin'] as $role)
<option value="{{ $role }}" @selected($user->role === $role)>{{ $role }}</option>
@endforeach
</select>
<select name="rbac_role" class="form-control" style="width:auto;" aria-label="RBAC role">
@foreach($assignableRoles as $rbac)
    @php
        $blocked = $rbac->name === 'super_admin' && ! auth()->user()?->isSuperAdmin();
    @endphp
    @unless($blocked)
        <option value="{{ $rbac->name }}" @selected($user->roles->contains('name', $rbac->name))>{{ $rbac->display_name }} ({{ $rbac->name }})</option>
    @endunless
@endforeach
</select>
<button class="btn btn-primary" type="submit" style="padding:.45rem .7rem;" onclick="return confirm('Confirm role change?')">Save</button>
</form>
    @endif
@else
<span style="color:var(--color-ink-muted);">Read only</span>
@endif
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $users->links() }}</div>
@endsection
