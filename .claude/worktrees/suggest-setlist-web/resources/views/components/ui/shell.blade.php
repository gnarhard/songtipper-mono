<div {{ $attributes->class('relative isolate min-h-screen overflow-hidden bg-canvas-light text-ink dark:bg-canvas-dark dark:text-ink-inverse') }}>
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
        <div class="absolute inset-0 bg-gradient-to-b from-accent-100 via-canvas-light to-canvas-light dark:from-surface-elevated dark:via-canvas-dark dark:to-canvas-dark"></div>
        <div class="absolute -left-20 top-0 h-80 w-80 rounded-full blur-3xl dark:bg-brand-500/10"></div>
        <div class="absolute inset-0 bg-[repeating-linear-gradient(90deg,rgba(48,41,56,0.06)_0_1px,transparent_1px_28px)] dark:bg-[repeating-linear-gradient(90deg,rgba(220,236,244,0.03)_0_1px,transparent_1px_28px)]"></div>
    </div>

    {{ $slot }}
</div>
