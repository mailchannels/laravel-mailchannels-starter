@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <h1>Dashboard</h1>
    <p>You are signed in as <strong>{{ auth()->user()->email }}</strong> and your email address is verified.</p>
@endsection
