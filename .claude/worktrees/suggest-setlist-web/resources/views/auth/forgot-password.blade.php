<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Song Tipper') }}</title>
    @include('partials.brand-meta')

    @include('partials.fonts')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse">
    <x-ui.shell>
        <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
            <x-ui.panel class="w-full max-w-[420px] p-6 sm:p-7 shadow-lg">
                <x-application-logo class="mx-auto h-12 w-12" />

                <h1 class="mt-3 text-center font-display text-3xl font-bold tracking-tight text-ink dark:text-ink-inverse">
                    Song Tipper
                </h1>
                <p class="mt-2 text-center text-sm text-ink-muted dark:text-ink-soft">
                    Enter your email and we'll send you a password reset link
                </p>

                @if (session('status'))
                    <x-ui.banner class="mt-6">
                        {{ session('status') }}
                    </x-ui.banner>
                @endif

                @if ($errors->any())
                    <x-ui.banner tone="error" class="mt-6">
                        <div class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10A8 8 0 1 1 2 10a8 8 0 0 1 16 0Zm-7-4a1 1 0 1 0-2 0v4a1 1 0 1 0 2 0V6Zm-2 7a1 1 0 1 1 2 0 1 1 0 0 1-2 0Z" clip-rule="evenodd" />
                            </svg>
                            <p>{{ $errors->first() }}</p>
                        </div>
                    </x-ui.banner>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                            Email
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-subtle dark:text-ink-soft">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M2.94 5.5A2.75 2.75 0 0 1 5.5 4h9a2.75 2.75 0 0 1 2.56 1.5L10 9.775 2.94 5.5Z" />
                                    <path d="M2 7.177V14.5A2.5 2.5 0 0 0 4.5 17h11a2.5 2.5 0 0 0 2.5-2.5V7.177l-7.48 4.525a1 1 0 0 1-1.04 0L2 7.177Z" />
                                </svg>
                            </span>
                            <x-text-input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="block w-full py-2.5 pl-10 pr-3 text-sm" />
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-primary-button class="w-full">
                        Email Password Reset Link
                    </x-primary-button>
                </form>

                <p class="mt-5 text-center text-sm text-ink-muted dark:text-ink-soft">
                    Remembered it?
                    <x-ui.text-link :href="route('login')">
                        Back to sign in
                    </x-ui.text-link>
                </p>
            </x-ui.panel>
        </main>
    </x-ui.shell>
</body>

</html>
