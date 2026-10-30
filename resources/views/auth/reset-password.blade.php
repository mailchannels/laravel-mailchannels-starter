@extends('layouts.app')
@section('title', 'Choose a new password')

@section('content')
    <h1>Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">
        <label for="password">New password (at least 8 characters)</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        <button type="submit">Reset password</button>
    </form>
@endsection
