<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl border border-ink-border-dark bg-surface px-4 py-2 text-xs font-semibold uppercase tracking-widest text-ink transition hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-ink/20 focus:ring-offset-2 focus:ring-offset-canvas-light disabled:opacity-25 dark:border-ink-border dark:bg-surface-inverse dark:text-ink-inverse dark:hover:bg-surface-elevated dark:focus:ring-ink-inverse/20 dark:focus:ring-offset-canvas-dark']) }}>
    {{ $slot }}
</button>
