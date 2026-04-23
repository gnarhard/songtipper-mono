@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-success-100 bg-success-50 px-3 py-2 text-sm font-medium text-success-700 dark:border-success-700/60 dark:bg-success-900/25 dark:text-success-300']) }}>
        {{ $status }}
    </div>
@endif
