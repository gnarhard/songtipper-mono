<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Song Tipper') }} - Get Paid More to Play</title>
    <meta name="description" content="Accept song requests with tips, manage your charts, setlists, and repertoire in one place. Let your audience choose what's next through a clear queue and simple tipping.">
    @include('partials.brand-meta')
    @include('partials.fonts')
    @vite('resources/css/app.css')
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse">
    @include('pages.partials.home-content')
    @vite('resources/js/app.js')
    @livewireScripts
</body>
</html>
