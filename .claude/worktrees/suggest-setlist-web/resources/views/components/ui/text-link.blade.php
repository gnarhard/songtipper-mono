@props(['href'])

<a
    href="{{ $href }}"
    {{ $attributes->class('font-medium text-ink underline decoration-ink-border-dark underline-offset-4 transition hover:text-ink-muted focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 focus:ring-offset-canvas-light dark:text-ink-inverse dark:decoration-ink-border dark:hover:text-ink-soft dark:focus:ring-brand-300 dark:focus:ring-offset-canvas-dark') }}
>
    {{ $slot }}
</a>
