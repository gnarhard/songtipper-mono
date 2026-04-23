<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $setlist->name }} — Shared Setlist | {{ config('app.name', 'Song Tipper') }}</title>
    @include('partials.brand-meta')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse">
    <x-ui.shell>
        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">

            {{-- Logo --}}
            <div class="mb-8 text-center">
                <a href="/">
                    <x-application-lockup
                        class="justify-center"
                        logo-class="h-12 w-auto"
                        text-class="font-display text-xl font-bold tracking-tight text-ink dark:text-ink-inverse"
                    />
                </a>
            </div>

            {{-- Header: setlist name, project name, top CTA --}}
            <x-ui.card class="p-6 text-center">
                <h1 class="font-display text-2xl font-bold text-ink dark:text-ink-inverse">
                    {{ $setlist->name }}
                </h1>
                <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
                    Shared from {{ $project->name }}
                </p>
                <div class="mt-5">
                    <x-ui.button-link :href="$deepLinkUrl">
                        Add to Your Project
                    </x-ui.button-link>
                </div>
            </x-ui.card>

            {{-- Sets and songs --}}
            @foreach ($setlist->sets as $set)
                <div class="mt-8">
                    <h2 class="mb-2 font-display text-lg font-semibold text-ink dark:text-ink-inverse">
                        {{ $set->name }}
                    </h2>

                    <x-ui.card class="divide-y divide-ink-border/40 dark:divide-ink-border-dark/40 overflow-hidden">
                        @forelse ($set->songs as $song)
                            @if ($song->project_song_id === null)
                                {{-- Note entry --}}
                                <div class="px-4 py-3">
                                    <p class="text-sm italic text-ink-muted dark:text-ink-soft">
                                        {{ $song->notes }}
                                    </p>
                                </div>
                            @else
                                {{-- Song entry --}}
                                <div class="flex items-center gap-3 px-4 py-3">
                                    @if ($song->color_hex)
                                        <span
                                            class="h-3 w-3 shrink-0 rounded-full"
                                            style="background-color: {{ $song->color_hex }}"
                                        ></span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-medium text-ink dark:text-ink-inverse">
                                            {{ $song->projectSong->title }}
                                            @if ($song->projectSong->version_label !== '' && $song->projectSong->version_label !== null)
                                                <span class="text-xs font-normal text-ink-muted dark:text-ink-soft">({{ $song->projectSong->version_label }})</span>
                                            @endif
                                        </div>
                                        @if ($song->projectSong->artist)
                                            <div class="truncate text-sm text-ink-muted dark:text-ink-soft">
                                                {{ $song->projectSong->artist }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="px-4 py-3 text-sm text-ink-muted dark:text-ink-soft">
                                No songs in this set.
                            </div>
                        @endforelse
                    </x-ui.card>
                </div>
            @endforeach

            {{-- Bottom CTA --}}
            <div class="mt-10 text-center">
                <x-ui.button-link :href="$deepLinkUrl">
                    Add to Your Project
                </x-ui.button-link>
                <p class="mt-3 text-xs text-ink-muted dark:text-ink-soft">
                    Opens in the Song Tipper app
                </p>
            </div>

        </div>
    </x-ui.shell>
</body>
</html>
