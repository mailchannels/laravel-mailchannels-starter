@extends('layouts.app')

@section('content')
    <h1>Laravel + MailChannels starter</h1>
    <p>This app sends its email through the <a href="https://docs.mailchannels.com/email-api/overview">MailChannels Email API</a>
        using the official <code>mailchannels/mailchannels-php</code> Laravel mail driver.</p>
    <ul>
        <li><a href="{{ route('signup') }}">Sign up</a> and verify your email address</li>
        <li><a href="{{ route('password.request') }}">Reset a forgotten password</a></li>
        <li><a href="{{ route('contact') }}">Send a contact form message</a></li>
        <li>Receive signed delivery events at <code>POST /webhooks/mailchannels</code></li>
    </ul>
@endsection
