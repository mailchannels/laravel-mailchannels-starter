@extends('layouts.app')
@section('title', 'Sign in')

@section('content')
    <h1>Sign in</h1>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <button type="submit">Sign in</button>
    </form>
    <p><a href="{{ route('password.request') }}">Forgot your password?</a></p>
@endsection
