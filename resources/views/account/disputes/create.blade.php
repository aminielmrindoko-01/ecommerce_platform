@extends('layouts.app')
@section('title', 'Open dispute')
@section('content')
@include('account._nav')
<div class="panel" style="max-width:520px;">
    <h1 class="font-display" style="margin-top:0;">Open dispute</h1>
    <p>Order {{ $order->order_number }}</p>
    @if($errors->any())<div class="panel" style="border-color:#b91c1c;">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('account.disputes.store', $order) }}">
        @csrf
        <label>Item (optional)</label>
        <select class="form-control" name="order_item_id">
            <option value="">Whole order / first vendor</option>
            @foreach($order->items as $item)
                <option value="{{ $item->id }}">{{ $item->displayName() }}</option>
            @endforeach
        </select>
        <label style="margin-top:.75rem;display:block;">Subject</label>
        <input class="form-control" name="subject" required maxlength="160">
        <label style="margin-top:.75rem;display:block;">Description</label>
        <textarea class="form-control" name="description" required maxlength="5000"></textarea>
        <button class="btn btn-primary" type="submit" style="margin-top:1rem;">Open dispute</button>
    </form>
</div>
@endsection
