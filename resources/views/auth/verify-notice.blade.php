@extends('layouts.app')
@section('title', 'Verify your email')

@section('content')
    <h1>Check your inbox</h1>
    <p>We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. The link expires in 24 hours.</p>
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit">Send a new link</button>
    </form>
@endsection
