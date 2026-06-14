@extends('layouts.app')

@section('content')

<div style="
    height:400px;
    background-image:url('/images/backgrounds/hero.jpg');
    background-size:cover;
    background-position:center;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
">

    <!-- DARK OVERLAY -->
    <div style="
        position:absolute;
        top:0;left:0;right:0;bottom:0;
        background:rgba(0,0,0,0.5);
        border-radius:10px;
    "></div>

    <!-- TEXT -->
    <div style="position:relative;text-align:center;color:white;">
        <h1 style="font-size:40px;">🛒 Shop Smart, Shop Fast</h1>
        <p>Buy and sell products anywhere in Tanzania</p>
        <a href="/products" style="text-decoration:none;">
    <button style="
        padding:10px 20px;
        background:orange;
        border:none;
        border-radius:5px;
        color:white;
        margin-top:10px;
        cursor:pointer;
    ">
        Start Shopping
    </button>
</a>
    </div>

</div>

@endsection