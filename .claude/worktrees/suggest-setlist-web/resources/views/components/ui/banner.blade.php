@props(['tone' => 'success'])

@php
    $classes = match ($tone) {
        'error' => 'rounded-xl border border-danger-100 bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:border-danger-700/60 dark:bg-danger-900/25 dark:text-danger-300',
        default => 'rounded-xl border border-success-100 bg-success-50 px-3 py-2 text-sm text-success-700 dark:border-success-700/60 dark:bg-success-900/25 dark:text-success-300',
    };
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>
