<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Song Tipper') }}</title>
    @include('partials.brand-meta')
    @include('partials.fonts')
    @vite('resources/css/app.css')
    @livewireStyles
    @stack('head')
</head>
<body class="min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse">
    <livewire:request-confirmation />
    @vite('resources/js/app.js')
    @livewireScripts
    @stack('scripts')
</body>
</html>
