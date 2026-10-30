@extends('layouts.app')
@section('title', $verified ? 'Email verified' : 'Link invalid')

@section('content')
    @if ($verified)
        <h1>Email verified</h1>
        <p>Thanks, your email address is confirmed. <a href="{{ route('dashboard') }}">Continue to your dashboard</a>.</p>
    @else
        <h1>This link is invalid or has expired</h1>
        <p>Verification links work once and expire after 24 hours. Sign in to request a new one.</p>
    @endif
@endsection
