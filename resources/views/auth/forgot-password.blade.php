@extends('layouts.app')
@section('title', 'Forgot password')

@section('content')
    <h1>Reset your password</h1>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        <button type="submit">Email me a reset link</button>
    </form>
@endsection
