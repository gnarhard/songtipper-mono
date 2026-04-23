<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl bg-danger-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-ink transition hover:bg-danger-100 focus:outline-none focus:ring-2 focus:ring-danger-500 focus:ring-offset-2 focus:ring-offset-canvas-light dark:bg-danger-500 dark:hover:bg-danger-100 dark:focus:ring-offset-canvas-dark']) }}>
    {{ $slot }}
</button>
