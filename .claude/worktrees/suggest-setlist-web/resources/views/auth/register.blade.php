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
                    Create your performer account and confirm your email before billing setup.
                </p>

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

                <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                            Name
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-subtle dark:text-ink-soft">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.696A18.683 18.683 0 0 1 12 22.5a18.683 18.683 0 0 1-7.812-1.699.75.75 0 0 1-.437-.696Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <x-text-input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" class="block w-full py-2.5 pl-10 pr-3 text-sm" />
                        </div>
                        @error('name')
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

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
                            <x-text-input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" class="block w-full py-2.5 pl-10 pr-3 text-sm" />
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="instrument_type" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                            Primary Instrument
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-subtle dark:text-ink-soft">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z" />
                                </svg>
                            </span>
                            <select id="instrument_type" name="instrument_type" required class="block w-full appearance-none rounded-lg border border-stroke bg-surface py-2.5 pl-10 pr-8 text-sm text-ink shadow-sm transition focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 dark:border-stroke-dark dark:bg-surface-inverse dark:text-ink-inverse">
                                <option value="">Select your instrument…</option>
                                @foreach (\App\Models\User::instrumentTypes() as $type)
                                    <option value="{{ $type }}" @selected(old('instrument_type') === $type)>{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('instrument_type')
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="secondary_instrument_type" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                            Secondary Instrument <span class="font-normal text-ink-subtle dark:text-ink-soft">(optional)</span>
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-subtle dark:text-ink-soft">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z" />
                                </svg>
                            </span>
                            <select id="secondary_instrument_type" name="secondary_instrument_type" class="block w-full appearance-none rounded-lg border border-stroke bg-surface py-2.5 pl-10 pr-8 text-sm text-ink shadow-sm transition focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 dark:border-stroke-dark dark:bg-surface-inverse dark:text-ink-inverse">
                                <option value="">None</option>
                                @foreach (\App\Models\User::instrumentTypes() as $type)
                                    <option value="{{ $type }}" @selected(old('secondary_instrument_type') === $type)>{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('secondary_instrument_type')
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                            Password
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-subtle dark:text-ink-soft">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25V9H6A2.25 2.25 0 0 0 3.75 11.25v8.25A2.25 2.25 0 0 0 6 21.75h12a2.25 2.25 0 0 0 2.25-2.25v-8.25A2.25 2.25 0 0 0 18 9h-.75V6.75A5.25 5.25 0 0 0 12 1.5Zm3.75 7.5V6.75a3.75 3.75 0 1 0-7.5 0V9h7.5Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" class="block w-full py-2.5 pl-10 pr-3 text-sm" />
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-subtle dark:text-ink-soft">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25V9H6A2.25 2.25 0 0 0 3.75 11.25v8.25A2.25 2.25 0 0 0 6 21.75h12a2.25 2.25 0 0 0 2.25-2.25v-8.25A2.25 2.25 0 0 0 18 9h-.75V6.75A5.25 5.25 0 0 0 12 1.5Zm3.75 7.5V6.75a3.75 3.75 0 1 0-7.5 0V9h7.5Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="block w-full py-2.5 pl-10 pr-3 text-sm" />
                        </div>
                        @error('password_confirmation')
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-primary-button class="w-full">
                        Create Account
                    </x-primary-button>
                </form>

                <p class="mt-5 text-center text-sm text-ink-muted dark:text-ink-soft">
                    Already registered?
                    <x-ui.text-link :href="route('login')">
                        Sign in
                    </x-ui.text-link>
                </p>
            </x-ui.panel>
        </main>
    </x-ui.shell>
</body>

</html>
