@props([
    'href',
    'variant' => 'primary',
])

@php
    $classes = match ($variant) {
        'secondary' => 'inline-flex items-center justify-center gap-2 rounded-xl border border-ink-border-dark bg-surface px-4 py-3 text-sm font-semibold text-ink transition hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-ink/20 focus:ring-offset-2 focus:ring-offset-canvas-light dark:border-ink-border dark:bg-surface-inverse dark:text-ink-inverse dark:hover:bg-surface-elevated dark:focus:ring-ink-inverse/20 dark:focus:ring-offset-canvas-dark',
        default => 'inline-flex items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3 text-sm font-semibold text-ink shadow-[0_0_10px_rgba(255,179,117,0.28)] transition hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2 focus:ring-offset-canvas-light dark:focus:ring-brand-200 dark:focus:ring-offset-canvas-dark',
    };
@endphp

<a href="{{ $href }}" {{ $attributes->class($classes) }}>
    {{ $slot }}
</a>
