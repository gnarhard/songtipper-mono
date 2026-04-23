<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Not Found - {{ config('app.name') }}</title>
    <meta name="description" content="We couldn't find the page you're looking for.">
    <meta name="robots" content="noindex, nofollow">
    @include('partials.brand-meta')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse">
    <x-ui.shell class="flex min-h-screen flex-col">
        <nav class="border-b border-ink-border bg-surface/60 backdrop-blur dark:border-ink-border-dark dark:bg-surface-inverse/60">
            <div class="mx-auto flex h-16 max-w-4xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}">
                    <x-application-lockup
                        logo-class="h-9 w-auto"
                        text-class="font-display text-xl font-bold tracking-tight text-ink dark:text-ink-inverse"
                    />
                </a>
                <div class="flex items-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm text-ink-muted transition hover:text-ink dark:text-ink-soft dark:hover:text-ink-inverse">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm text-ink-muted transition hover:text-ink dark:text-ink-soft dark:hover:text-ink-inverse">Login</a>
                        <x-ui.button-link :href="route('register')" class="px-4 py-2 text-sm">Register</x-ui.button-link>
                    @endauth
                </div>
            </div>
        </nav>

        <main class="flex flex-1 items-center justify-center px-4 py-16 sm:px-6 lg:px-8">
            <x-ui.panel class="w-full max-w-2xl overflow-hidden px-8 py-12 text-center sm:px-12 sm:py-16">
                <p class="font-display text-sm font-semibold uppercase tracking-[0.25em] text-brand">
                    Off the setlist
                </p>

                <h1 class="mt-4 font-display text-[96px] font-bold leading-none tracking-tight text-ink dark:text-ink-inverse sm:text-[128px]">
                    4<span class="text-brand">0</span>4
                </h1>

                <h2 class="mt-6 font-display text-2xl font-bold tracking-tight text-ink dark:text-ink-inverse sm:text-3xl">
                    This track isn't in our repertoire.
                </h2>

                <p class="mx-auto mt-4 max-w-md text-base text-ink-muted dark:text-ink-soft">
                    The page you're looking for may have been moved, renamed, or never existed. Let's get you back to the stage.
                </p>

                <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <x-ui.button-link :href="route('home')" class="w-full sm:w-auto">
                        Back to home
                    </x-ui.button-link>
                    <x-ui.button-link :href="route('blog.index')" variant="secondary" class="w-full sm:w-auto">
                        Read the blog
                    </x-ui.button-link>
                </div>
            </x-ui.panel>
        </main>

        @include('partials.site-footer-min')
    </x-ui.shell>
</body>
</html>
