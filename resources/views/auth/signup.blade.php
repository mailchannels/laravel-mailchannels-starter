@extends('layouts.app')
@section('title', 'Sign up')

@section('content')
    <h1>Create an account</h1>
    <form method="POST" action="{{ route('signup') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        <label for="password">Password (at least 8 characters)</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        <button type="submit">Sign up</button>
    </form>
@endsection
