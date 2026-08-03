@extends('layouts.app')
@section('title', 'Request return')
@section('content')
@include('account._nav')
<div class="panel" style="max-width:520px;">
    <h1 class="font-display" style="margin-top:0;">Request return</h1>
    <p>{{ $item->displayName() }} · Order {{ $order->order_number }}</p>
    @if(!$eligibility['ok'])
        <p style="color:#b91c1c;">{{ $eligibility['reason'] }}</p>
    @else
        @if($errors->any())<div class="panel" style="border-color:#b91c1c;">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('account.returns.store', [$order, $item]) }}">
            @csrf
            <label>Quantity (max {{ $eligibility['max_qty'] }})</label>
            <input class="form-control" type="number" name="quantity" min="1" max="{{ $eligibility['max_qty'] }}" value="1" required>
            <label style="margin-top:.75rem;display:block;">Reason</label>
            <textarea class="form-control" name="reason" required maxlength="1000"></textarea>
            <button class="btn btn-primary" type="submit" style="margin-top:1rem;">Submit return</button>
        </form>
    @endif
</div>
@endsection
