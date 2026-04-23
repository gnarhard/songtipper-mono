<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl bg-brand px-4 py-2 text-xs font-semibold uppercase tracking-widest text-ink shadow-[0_0_10px_rgba(255,179,117,0.28)] transition hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2 focus:ring-offset-canvas-light dark:focus:ring-brand-200 dark:focus:ring-offset-canvas-dark']) }}>
    {{ $slot }}
</button>
