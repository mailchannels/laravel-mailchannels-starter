<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Home') · {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 36rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; color: #1f2937; }
        nav { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; margin-bottom: 2rem; }
        nav form { margin: 0; }
        label { display: block; margin-top: 1rem; font-weight: 600; }
        input, textarea { width: 100%; padding: .5rem; box-sizing: border-box; font: inherit; }
        button { margin-top: 1rem; padding: .5rem 1rem; font: inherit; cursor: pointer; }
        nav button { margin: 0; padding: 0; border: 0; background: none; color: #2563eb; text-decoration: underline; }
        .status { background: #ecfdf5; border: 1px solid #10b981; padding: .75rem; }
        .errors { background: #fef2f2; border: 1px solid #ef4444; padding: .75rem; }
    </style>
</head>
<body>
<nav>
    <a href="{{ route('home') }}"><strong>{{ config('app.name') }}</strong></a>
    <a href="{{ route('contact') }}">Contact</a>
    @auth
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
    @else
        <a href="{{ route('signup') }}">Sign up</a>
        <a href="{{ route('login') }}">Sign in</a>
    @endauth
</nav>

@if (session('status'))
    <p class="status" role="status">{{ session('status') }}</p>
@endif

@if ($errors->any())
    <div class="errors" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@yield('content')
</body>
</html>
