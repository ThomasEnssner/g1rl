<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="g1rl">
        <meta property="og:title" content="Gothic 1 Remake Lockpicker">
        <meta property="og:description" content="{{ __('Enter the start position of the lock and its movement rules. The solver runs a breadth first search and always finds the shortest sequence that moves every pin to 4.') }}">
        <meta property="og:url" content="{{ url()->full() }}">
        <meta property="og:image" content="{{ asset('og-image.png') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="description" content="{{ __('Enter the start position of the lock and its movement rules. The solver runs a breadth first search and always finds the shortest sequence that moves every pin to 4.') }}">
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
