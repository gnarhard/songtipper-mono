<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Song Tipper') }}</title>
        @include('partials.brand-meta')

        @include('partials.fonts')

        <!-- Scripts -->
        @vite(['resources/css/app.css'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse">
        <x-ui.shell>
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-ink-border/70 bg-surface/95 shadow-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/95">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </x-ui.shell>
        @livewireScripts
        @vite(['resources/js/app.js'])
    </body>
</html>
