@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-brand-600 dark:border-brand-300 text-start text-base font-medium text-brand-700 dark:text-brand-100 bg-brand-50 dark:bg-brand-900/40 focus:outline-none focus:text-brand-700 dark:focus:text-brand-50 focus:bg-brand-100 dark:focus:bg-brand-900/60 focus:border-brand-700 dark:focus:border-brand-100 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-ink-muted dark:text-ink-soft hover:text-ink dark:hover:text-ink-inverse hover:bg-surface-muted dark:hover:bg-surface-elevated hover:border-ink-border dark:hover:border-ink-border-dark focus:outline-none focus:text-ink dark:focus:text-ink-inverse focus:bg-surface-muted dark:focus:bg-surface-elevated focus:border-ink-border dark:focus:border-ink-border-dark transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
