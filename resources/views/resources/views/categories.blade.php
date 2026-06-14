@extends('layouts.app')

@section('content')

<h1>📂 Categories</h1>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-top:20px;">

    <div style="background:white;padding:15px;border-radius:10px;text-align:center;">
        📱 Electronics
    </div>

    <div style="background:white;padding:15px;border-radius:10px;text-align:center;">
        👕 Fashion
    </div>

    <div style="background:white;padding:15px;border-radius:10px;text-align:center;">
        🏠 Home
    </div>

    <div style="background:white;padding:15px;border-radius:10px;text-align:center;">
        💄 Beauty
    </div>

</div>

@endsection