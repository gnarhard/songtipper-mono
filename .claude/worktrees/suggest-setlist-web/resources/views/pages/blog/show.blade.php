<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $article['title'] }} - {{ config('app.name') }}</title>
    <meta name="description" content="{{ $article['meta_description'] }}">
    <meta name="robots" content="noindex, nofollow">
    @include('partials.brand-meta')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse">
    <x-ui.shell>
    <div class="min-h-screen">
        <nav class="border-b border-ink-border bg-surface dark:border-ink-border-dark dark:bg-surface-inverse">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('home') }}">
                            <x-application-lockup
                                logo-class="h-9 w-auto"
                                text-class="font-display text-xl font-bold tracking-tight text-ink dark:text-ink-inverse"
                            />
                        </a>
                    </div>
                    <div class="flex items-center space-x-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-ink-muted transition hover:text-ink dark:text-ink-soft dark:hover:text-ink-inverse">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="text-ink-muted transition hover:text-ink dark:text-ink-soft dark:hover:text-ink-inverse">Login</a>
                            <x-ui.button-link :href="route('register')" class="px-4 py-2">Register</x-ui.button-link>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <a href="{{ route('blog.index') }}" class="mb-8 inline-flex items-center text-sm font-medium text-ink-muted transition hover:text-ink dark:text-ink-soft dark:hover:text-ink-inverse">
                &larr; Back to Blog
            </a>

            <x-ui.card class="p-8">
                <p class="text-sm text-ink-muted dark:text-ink-soft">
                    {{ \Carbon\Carbon::parse($article['published_at'])->format('F j, Y') }}
                </p>
                <h1 class="mt-2 mb-8 font-display text-3xl font-bold text-ink dark:text-ink-inverse sm:text-4xl">
                    {{ $article['title'] }}
                </h1>

                @include('blog.articles.' . $article['slug'])
            </x-ui.card>

            {{-- CTA --}}
            @php($trialDays = (int) config('billing.trial_days'))
            <section class="mt-12 rounded-3xl border border-brand-200/60 bg-gradient-to-br from-brand-50 to-surface p-10 text-center dark:border-white/10 dark:from-surface-inverse dark:to-canvas-dark">
                <h2 class="font-display text-2xl font-bold text-ink dark:text-ink-inverse sm:text-3xl">
                    Ready to 10x Your Tips?
                </h2>
                <p class="mt-4 text-lg text-ink-muted dark:text-ink-soft">
                    Try Song Tipper free for {{ $trialDays }} days.
                </p>
                <x-ui.button-link :href="route('register')" class="mt-6 inline-flex px-8 py-4 text-lg shadow-lg">
                    Start Your Free Trial
                </x-ui.button-link>
            </section>
        </main>

        @include('partials.site-footer-min')
    </div>
    </x-ui.shell>
</body>
</html>
