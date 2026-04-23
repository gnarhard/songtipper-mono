<nav x-data="{ open: false }" class="border-b border-ink-border/70 bg-surface/95 backdrop-blur dark:border-ink-border-dark/80 dark:bg-surface-inverse/95">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <x-application-lockup
                            logo-class="h-9 w-auto"
                            text-class="font-display text-lg font-bold tracking-tight text-ink dark:text-ink-inverse"
                        />
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Beta</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 lg:-my-px lg:ms-10 lg:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('dashboard.billing.show')" :active="request()->routeIs('dashboard.billing.*')">
                        {{ __('Billing') }}
                    </x-nav-link>
                    @if (Auth::user()->isAdmin())
                        <x-nav-link :href="route('admin.access')" :active="request()->routeIs('admin.access')">
                            {{ __('Access') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.songs')" :active="request()->routeIs('admin.songs')">
                            {{ __('Songs') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.song-integrity')" :active="request()->routeIs('admin.song-integrity')">
                            {{ __('Integrity') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.test-checklist')" :active="request()->routeIs('admin.test-checklist')">
                            {{ __('Test Checklist') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden lg:flex lg:items-center lg:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button data-test="nav-user-menu-trigger" class="inline-flex items-center rounded-xl border border-transparent bg-surface px-3 py-2 text-sm font-medium leading-4 text-ink-muted transition ease-in-out duration-150 hover:text-ink dark:bg-surface-inverse dark:text-ink-soft dark:hover:text-ink-inverse focus:outline-none">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center lg:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-xl p-2 text-ink-soft transition duration-150 ease-in-out hover:bg-surface-muted hover:text-ink dark:text-ink-soft dark:hover:bg-surface-elevated dark:hover:text-ink-inverse focus:outline-none focus:bg-surface-muted dark:focus:bg-surface-elevated focus:text-ink dark:focus:text-ink-inverse">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('dashboard.billing.show')" :active="request()->routeIs('dashboard.billing.*')">
                {{ __('Billing') }}
            </x-responsive-nav-link>
            @if (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('admin.songs')" :active="request()->routeIs('admin.songs')">
                    {{ __('Songs') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.access')" :active="request()->routeIs('admin.access')">
                    {{ __('Access') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.song-integrity')" :active="request()->routeIs('admin.song-integrity')">
                    {{ __('Integrity') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.test-checklist')" :active="request()->routeIs('admin.test-checklist')">
                    {{ __('Test Checklist') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="border-t border-ink-border/70 pt-4 pb-1 dark:border-ink-border-dark/80">
            <div class="px-4">
                <div class="text-base font-medium text-ink dark:text-ink-inverse">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-ink-muted dark:text-ink-soft">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
