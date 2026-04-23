@php $embed = request()->boolean('embed'); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    @include('partials.brand-meta')
    @include('partials.fonts')
    @vite('resources/css/app.css')
    @livewireStyles
    @if ($embed)
    <style>
        /* Neutral embed overrides — works on any site background */
        body.st-embed {
            background: transparent !important;
            color: #1f1f1f !important;
            min-height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .st-embed .st-embed-card {
            background: #fafafa !important;
            border-color: #e0e0e0 !important;
        }
        .st-embed .st-embed-header-row {
            background: #f3f3f3 !important;
            border-color: #e0e0e0 !important;
            color: #6b6b6b !important;
        }
        .st-embed .st-embed-row {
            border-color: #ebebeb !important;
        }
        .st-embed .st-embed-title {
            color: #1f1f1f !important;
        }
        .st-embed .st-embed-text {
            color: #333 !important;
        }
        .st-embed .st-embed-muted {
            color: #888 !important;
        }
        .st-embed .st-embed-badge-instrumental {
            background: #e8f4f8 !important;
            color: #2a7d8c !important;
        }
        .st-embed .st-embed-badge-mashup {
            background: #fce8e8 !important;
            color: #b44040 !important;
        }
        .st-embed .st-embed-powered {
            color: #999 !important;
        }
        .st-embed .st-embed-powered:hover {
            color: #666 !important;
        }

        @media (prefers-color-scheme: dark) {
            body.st-embed {
                color: #e8e8e8 !important;
            }
            .st-embed .st-embed-card {
                background: #1e1e1e !important;
                border-color: #333 !important;
            }
            .st-embed .st-embed-header-row {
                background: #252525 !important;
                border-color: #333 !important;
                color: #999 !important;
            }
            .st-embed .st-embed-row {
                border-color: #2a2a2a !important;
            }
            .st-embed .st-embed-title {
                color: #e8e8e8 !important;
            }
            .st-embed .st-embed-text {
                color: #ccc !important;
            }
            .st-embed .st-embed-muted {
                color: #777 !important;
            }
            .st-embed .st-embed-badge-instrumental {
                background: rgba(42, 125, 140, 0.2) !important;
                color: #6ec4d4 !important;
            }
            .st-embed .st-embed-badge-mashup {
                background: rgba(180, 64, 64, 0.2) !important;
                color: #e88 !important;
            }
            .st-embed .st-embed-powered {
                color: #666 !important;
            }
            .st-embed .st-embed-powered:hover {
                color: #999 !important;
            }
        }

        /* Scrollbar — light & dark */
        body.st-embed {
            scrollbar-width: thin;
            scrollbar-color: #ccc transparent;
        }
        body.st-embed::-webkit-scrollbar { width: 6px; }
        body.st-embed::-webkit-scrollbar-track { background: transparent; }
        body.st-embed::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
        body.st-embed::-webkit-scrollbar-thumb:hover { background: #aaa; }

        @media (prefers-color-scheme: dark) {
            body.st-embed {
                scrollbar-color: #444 transparent;
            }
            body.st-embed::-webkit-scrollbar-thumb { background: #444; }
            body.st-embed::-webkit-scrollbar-thumb:hover { background: #666; }
        }
    </style>
    @endif
    @stack('head')
</head>
<body @class([
    'font-sans antialiased',
    'min-h-screen bg-canvas-light text-ink dark:bg-canvas-dark dark:text-ink-inverse' => !$embed,
    'st-embed' => $embed,
])>
    <livewire:project-page :projectSlug="$projectSlug" mode="repertoire" :embed="$embed" />
    @vite('resources/js/app.js')
    @livewireScripts
    @stack('scripts')
</body>
</html>
