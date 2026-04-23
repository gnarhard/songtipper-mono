@props([
    'logoClass' => 'h-10 w-auto',
    'textClass' => 'font-display text-xl font-bold tracking-tight text-ink dark:text-ink-inverse',
])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    <x-application-logo class="{{ $logoClass }}" />
    <span class="{{ $textClass }}">{{ config('app.name') }}</span>
</span>
