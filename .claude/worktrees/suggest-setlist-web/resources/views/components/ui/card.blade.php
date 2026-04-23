@props(['tone' => 'default'])

@php
    $classes = match ($tone) {
        'muted' => 'rounded-2xl border border-ink-border/80 bg-surface-muted/95 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-elevated/90',
        default => 'rounded-2xl border border-ink-border/80 bg-surface/95 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90',
    };
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>
