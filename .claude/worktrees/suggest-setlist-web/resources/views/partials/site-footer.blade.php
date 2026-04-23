<footer class="border-t border-ink-border/70 bg-canvas-light py-16 dark:border-ink-border-dark/80 dark:bg-canvas-dark">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12">
            {{-- Product --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-ink dark:text-ink-soft">Product</h3>
                <ul class="space-y-3">
                    <li><a href="{{ route('home') }}#pricing" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">Pricing</a></li>
                    <li><a href="{{ route('home') }}#search" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">Find a Performer</a></li>
                    <li><a href="{{ route('blog.index') }}" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">Blog</a></li>
                </ul>
            </div>

            {{-- Legal --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-ink dark:text-ink-soft">Legal</h3>
                <ul class="space-y-3">
                    <li><a href="{{ route('terms') }}" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">Terms of Service</a></li>
                    <li><a href="{{ route('privacy') }}" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">Privacy Policy</a></li>
                    <li><a href="{{ route('eula') }}" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">EULA</a></li>
                </ul>
            </div>

            {{-- Account --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-ink dark:text-ink-soft">Account</h3>
                <ul class="space-y-3">
                    @auth
                        <li><a href="{{ route('dashboard') }}" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">Dashboard</a></li>
                        <li><a href="{{ route('profile.edit') }}" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">Profile</a></li>
                    @else
                        <li><a href="{{ route('register') }}" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">Register</a></li>
                        <li><a href="{{ route('login') }}" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">Login</a></li>
                    @endauth
                </ul>
            </div>

            {{-- Support --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-ink dark:text-ink-soft">Support</h3>
                <ul class="space-y-3">
                    <li><a href="{{ route('home') }}#contact" class="text-ink-muted transition hover:text-brand dark:text-ink-soft dark:hover:text-brand-300">Contact Us</a></li>
                </ul>
            </div>
        </div>

        <div class="border-t border-ink-border/60 pt-8 dark:border-ink-border-dark/80">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <a href="{{ route('home') }}">
                    <x-application-lockup logo-class="h-10 w-auto" text-class="font-display text-2xl font-bold text-ink dark:text-surface" />
                </a>
                <p class="text-sm text-ink-muted dark:text-ink-soft">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</footer>
