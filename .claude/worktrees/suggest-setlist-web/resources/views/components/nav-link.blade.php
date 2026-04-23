@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-brand-600 dark:border-brand-300 text-sm font-medium leading-5 text-ink dark:text-ink-inverse focus:outline-none focus:border-brand-700 dark:focus:border-brand-100 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-ink-muted dark:text-ink-soft hover:text-ink dark:hover:text-ink-inverse hover:border-ink-border dark:hover:border-ink-border-dark focus:outline-none focus:text-ink dark:focus:text-ink-inverse focus:border-ink-border dark:focus:border-ink-border-dark transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
