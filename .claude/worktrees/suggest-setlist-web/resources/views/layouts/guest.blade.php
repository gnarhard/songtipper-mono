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
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse">
        <x-ui.shell class="flex min-h-screen flex-col items-center pt-6 sm:justify-center sm:pt-0">
            <div>
                <a href="/">
                    <x-application-lockup
                        class="justify-center"
                        logo-class="h-16 w-auto"
                        text-class="font-display text-2xl font-bold tracking-tight text-ink dark:text-ink-inverse"
                    />
                </a>
            </div>

            <x-ui.panel class="mt-6 w-full overflow-hidden px-6 py-4 sm:max-w-md">
                {{ $slot }}
            </x-ui.panel>
        </x-ui.shell>
    </body>
</html>
