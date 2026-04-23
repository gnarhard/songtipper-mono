<footer class="mt-12 border-t border-ink-border bg-canvas-light py-8 dark:border-ink-border-dark dark:bg-canvas-dark">
    <div class="mx-auto max-w-4xl px-4 text-center text-sm text-ink-muted dark:text-ink-soft sm:px-6 lg:px-8">
        <div class="flex justify-center space-x-6 mb-4">
            <a href="{{ route('home') }}" class="transition hover:text-ink dark:hover:text-ink-inverse">Home</a>
            <a href="{{ route('terms') }}" class="transition hover:text-ink dark:hover:text-ink-inverse">Terms</a>
            <a href="{{ route('privacy') }}" class="transition hover:text-ink dark:hover:text-ink-inverse">Privacy</a>
            <a href="{{ route('eula') }}" class="transition hover:text-ink dark:hover:text-ink-inverse">EULA</a>
        </div>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</footer>
