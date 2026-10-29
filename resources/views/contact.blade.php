@extends('layouts.app')
@section('title', 'Contact')

@section('content')
    <h1>Contact us</h1>
    <form method="POST" action="{{ route('contact') }}">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        <label for="message">Message</label>
        <textarea id="message" name="message" rows="6" required maxlength="5000">{{ old('message') }}</textarea>
        <button type="submit">Send</button>
    </form>
@endsection
