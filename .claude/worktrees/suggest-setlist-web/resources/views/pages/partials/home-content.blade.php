<x-ui.shell>
    @php($planGroups = app(\App\Support\BillingPlanCatalog::class)->allTierGroups())
    @php($trialDays = (int) config('billing.trial_days'))
    @php($latestArticles = app(\App\Support\BlogArticleCatalog::class)->latest(3))

    <style>
        .hero-stream {
            will-change: transform;
            transform: translate3d(0, 0, 0);
        }

        .hero-stream--one {
            animation: hero-sine-one 16s linear infinite;
        }

        .hero-stream--two {
            animation: hero-sine-two 13s linear infinite;
        }

        .hero-stream--three {
            animation: hero-sine-three 11s linear infinite;
        }

        @keyframes hero-sine-one {
            0% {
                transform: translate3d(-1.6%, 0, 0);
            }

            25% {
                transform: translate3d(-0.8%, 7px, 0);
            }

            50% {
                transform: translate3d(0, 0, 0);
            }

            75% {
                transform: translate3d(-0.8%, -7px, 0);
            }

            100% {
                transform: translate3d(-1.6%, 0, 0);
            }
        }

        @keyframes hero-sine-two {
            0% {
                transform: translate3d(1.2%, 0, 0);
            }

            25% {
                transform: translate3d(0.6%, -9px, 0);
            }

            50% {
                transform: translate3d(0, 0, 0);
            }

            75% {
                transform: translate3d(0.6%, 9px, 0);
            }

            100% {
                transform: translate3d(1.2%, 0, 0);
            }
        }

        @keyframes hero-sine-three {
            0% {
                transform: translate3d(-1%, 0, 0);
            }

            25% {
                transform: translate3d(-0.5%, 6px, 0);
            }

            50% {
                transform: translate3d(0, 0, 0);
            }

            75% {
                transform: translate3d(-0.5%, -6px, 0);
            }

            100% {
                transform: translate3d(-1%, 0, 0);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .hero-stream {
                animation: none !important;
                transform: none !important;
            }
        }

        .slant-crop-10 {
            clip-path: polygon(0 8.8vw, 100% 0, 100% calc(100% - 8.8vw), 0 100%);
        }

        .slant-crop--10 {
            clip-path: polygon(0 0, 100% 8.8vw, 100% 100%, 0 calc(100% - 8.8vw));
        }

        .slant-spacing {
            margin-top: -9vw;
            margin-bottom: -9vw;
            padding-top: calc(12rem + 8.8vw);
            padding-bottom: calc(12rem + 8.8vw);
        }

        .slant-edge-blend-top {
            pointer-events: none;
            position: absolute;
            inset-inline: 0;
            top: 0;
            height: clamp(3.25rem, 10vw, 8rem);
        }

        .slant-edge-blend-bottom {
            pointer-events: none;
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            height: clamp(3.25rem, 10vw, 8rem);
        }

        @media (min-width: 1024px) {
            .slant-crop-10 {
                clip-path: polygon(0 6.2vw, 100% 0, 100% calc(100% - 6.2vw), 0 100%);
            }

            .slant-crop--10 {
                clip-path: polygon(0 0, 100% 6.2vw, 100% 100%, 0 calc(100% - 6.2vw));
            }

            .slant-spacing {
                margin-top: -6.5vw;
                margin-bottom: -6.5vw;
                padding-top: calc(12rem + 6.2vw);
                padding-bottom: calc(12rem + 6.2vw);
            }
        }
    </style>

    {{-- Hero Section --}}
    <section class="relative overflow-hidden border-b border-ink-border/70 bg-gradient-to-br from-white/30 via-surface to-accent-50/50 dark:border-ink-border-dark/80 dark:bg-none dark:from-canvas-dark dark:via-surface-inverse dark:to-brand-900">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(220,236,244,0.5),_transparent_48%)] dark:bg-[radial-gradient(circle_at_top,_rgba(220,236,244,0.18),_transparent_48%)]"></div>
        <div class="absolute inset-0 flex items-center">
            <svg viewBox="0 0 1440 440" xmlns="http://www.w3.org/2000/svg" class="w-full" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="stream1" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#ffffff" stop-opacity="0" />
                        <stop offset="40%" stop-color="#d4dce3" stop-opacity=".8" />
                        <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="stream2" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#ffffff" stop-opacity="0" />
                        <stop offset="50%" stop-color="#d9e0e8" stop-opacity="0.9" />
                        <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="stream3" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#ffffff" stop-opacity="0" />
                        <stop offset="60%" stop-color="#dfe5ed" stop-opacity="0.95" />
                        <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                    </linearGradient>
                </defs>
                {{-- Stream 1 --}}
                <path class="hero-stream hero-stream--one" d="M0,100 C200,20 400,260 600,140 C800,20 1000,240 1200,120 C1320,60 1390,160 1440,130 L1440,230 C1390,260 1320,160 1200,220 C1000,340 800,120 600,240 C400,360 200,120 0,200 Z" fill="url(#stream1)" />
                {{-- Stream 2 --}}
                <path class="hero-stream hero-stream--two" d="M0,170 C180,300 360,60 540,190 C720,320 900,80 1080,190 C1240,280 1370,160 1440,200 L1440,280 C1370,240 1240,360 1080,270 C900,160 720,400 540,270 C360,140 180,380 0,250 Z" fill="url(#stream2)" />
                {{-- Stream 3 --}}
                <path class="hero-stream hero-stream--three" d="M0,300 C240,200 480,380 720,260 C960,140 1200,340 1440,220 L1440,320 C1200,440 960,220 720,360 C480,500 240,300 0,400 Z" fill="url(#stream3)" />
            </svg>
        </div>
        <div class="absolute inset-0 bg-black/5 dark:bg-black/50"></div>

        <nav class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('home') }}" class="min-w-0 flex-1">
                    <x-application-lockup logo-class="h-9 w-auto sm:h-10" text-class="font-display text-lg sm:text-2xl font-bold leading-none whitespace-nowrap text-ink dark:text-surface" />
                </a>
                <div class="flex shrink-0 items-center justify-end gap-2 sm:gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="whitespace-nowrap text-sm text-ink-muted transition hover:text-ink dark:text-surface/80 dark:hover:text-surface sm:text-base">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="whitespace-nowrap text-sm text-ink-muted transition hover:text-ink dark:text-surface/80 dark:hover:text-surface sm:text-base">Login</a>
                        <x-ui.button-link :href="route('register')" class="whitespace-nowrap px-2.5 py-2 text-sm sm:px-4 sm:text-base">
                            Get Started
                        </x-ui.button-link>
                    @endauth
                </div>
            </div>
        </nav>

        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 sm:py-32">
            <div class="text-center">
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-display font-bold text-ink dark:text-white mb-6">
                    If you're gonna play for money, might as well be good at it.
                </h1>
                <p class="mb-8 text-lg text-accent [text-shadow:0_2px_10px_rgba(255,255,255,0.28)] dark:text-brand-100 dark:[text-shadow:0_2px_12px_rgba(7,10,20,0.42)] sm:text-xl">
                    Built by a musician, for musicians.
                </p>
                <div class="mx-auto mb-10 max-w-3xl text-xl text-ink-soft sm:text-2xl">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl mx-auto">
                        <div class="rounded-lg border border-brand-200/60 bg-white/60 p-4 backdrop-blur-sm dark:border-white/10 dark:bg-accent/90">
                            <svg class="mx-auto mb-2 h-8 w-8 text-accent-500 dark:text-accent-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5c.414 0 .75.336.75.75v9a.75.75 0 01-.75.75H3.75A.75.75 0 013 16.5v-9c0-.414.336-.75.75-.75zM12 9v6m-1.875-4.5c0-.621.504-1.125 1.125-1.125h1.5c.621 0 1.125.504 1.125 1.125s-.504 1.125-1.125 1.125h-1.5c-.621 0-1.125.504-1.125 1.125s.504 1.125 1.125 1.125h1.5c.621 0 1.125-.504 1.125-1.125" />
                            </svg>
                            <p class="text-center text-lg text-ink dark:text-surface">Let guests request and tip easily</p>
                        </div>
                        <div class="rounded-lg border border-brand-200/60 bg-white/60 p-4 backdrop-blur-sm dark:border-white/10 dark:bg-accent/90">
                            <svg class="mx-auto mb-2 h-6 w-6 text-accent-500 dark:text-accent-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-center text-lg text-ink dark:text-surface">Save time on setlists and charts</p>
                        </div>
                        <div class="rounded-lg border border-brand-200/60 bg-white/60 p-4 backdrop-blur-sm dark:border-white/10 dark:bg-accent/90">
                            <svg class="mx-auto mb-2 h-6 w-6 text-accent-500 dark:text-accent-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                                <circle cx="9" cy="7" r="4" stroke-width="2" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
                            </svg>
                            <p class="text-center text-lg text-ink dark:text-surface">Your band on the same page</p>
                        </div>
                        <div class="rounded-lg border border-brand-200/60 bg-white/60 p-4 backdrop-blur-sm dark:border-white/10 dark:bg-accent/90">
                            <svg class="mx-auto mb-2 h-6 w-6 text-accent-500 dark:text-accent-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                            </svg>
                            <p class="text-center text-lg text-ink dark:text-surface">Built by a touring musician</p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <x-ui.button-link :href="route('register')" class="px-8 py-4 text-lg shadow-lg">
                        Get Started Free
                    </x-ui.button-link>
                    <a href="#features" class="inline-flex items-center justify-center rounded-xl border-2 border-accent px-8 py-4 text-lg font-semibold text-ink transition hover:bg-brand-100 dark:border-white/20 dark:text-surface dark:hover:bg-white/8">
                        Learn More
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- About the Creator --}}
    <section class="relative z-[1] bg-canvas-light pt-20 pb-[calc(5rem+4vw)] dark:bg-canvas-dark">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-3xl border border-ink-border bg-surface dark:border-ink-border-dark dark:bg-gradient-to-br dark:from-surface-inverse dark:via-surface-inverse dark:to-canvas-dark">
                <div class="flex flex-col md:flex-row">
                    <div class="relative min-h-[240px] sm:min-h-[320px] md:min-h-[420px] md:basis-1/2 md:flex-none md:shrink-0 overflow-hidden">
                        <img src="{{ asset('images/grayson_erhard_songtipper.webp') }}" alt="Grayson Erhard" class="absolute inset-0 h-full w-full object-cover object-top" loading="lazy">
                    </div>
                    <div class="p-8 sm:p-10 lg:p-12 flex flex-col justify-center md:flex-1">
                        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-accent-500 dark:text-accent-300">
                            About the Creator
                        </p>
                        <h2 class="mt-3 font-display text-3xl font-bold text-ink dark:text-ink-inverse sm:text-4xl">
                            Built by Grayson Erhard
                        </h2>
                        <div class="mt-6 space-y-4 text-lg leading-relaxed text-ink-muted dark:text-ink-soft">
                            <p>
                                I'm a full-time touring musician with over a decade of experience in software development. I started building this project when I switched to music full-time because the other tools simply didn't do everything I needed. I only have so many gigs each month, and maximizing my earnings at each one makes a massive difference. Since using this platform, I've reliably multiplied my tip income by 10x. I also no longer have to pull my hair out dealing with the inefficiencies of managing my repertoire across musical projects, charts, and setlists. Song Tipper is the tool I wish I'd had from day one.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Audience Testimonials --}}
    <section class="relative z-[2] overflow-hidden bg-cover bg-center bg-no-repeat slant-crop--10 slant-spacing bg-surface dark:bg-surface-inverse" style="background-image: url('{{ asset('images/audience.webp') }}');">
        <div class="absolute inset-0 bg-black/80"></div>
        <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="font-display text-3xl font-bold text-ink-soft sm:text-4xl">
                    What the Audience is Saying.
                </h2>
                <p class="mt-4 text-lg text-ink-soft">
                    Audiences love how easy, interactive, and fun the request experience feels.
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <figure class="rounded-2xl border border-ink-border bg-surface-muted/80 p-6 shadow-sm dark:border-ink-border-dark dark:bg-surface-elevated/80">
                    <blockquote class="text-lg leading-relaxed text-ink dark:text-ink-inverse">
                        "It's like having a jukebox with a real person."
                    </blockquote>
                </figure>

                <figure class="rounded-2xl border border-ink-border bg-surface-muted/80 p-6 shadow-sm dark:border-ink-border-dark dark:bg-surface-elevated/80">
                    <blockquote class="text-lg leading-relaxed text-ink dark:text-ink-inverse">
                        "I love how I don't even have to get up from my seat to request songs."
                    </blockquote>
                </figure>

                <figure class="rounded-2xl border border-ink-border bg-surface-muted/80 p-6 shadow-sm dark:border-ink-border-dark dark:bg-surface-elevated/80">
                    <blockquote class="text-lg leading-relaxed text-ink dark:text-ink-inverse">
                        "I love that I can hear my song faster and the musician earns more. It's a win-win!"
                    </blockquote>
                </figure>
            </div>
        </div>
    </section>

    {{-- Pricing Section --}}
    <section id="pricing" class="relative z-[1] bg-surface pt-[calc(5rem+4vw)] pb-[calc(5rem+4vw)] dark:bg-surface-inverse">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="mb-4 font-display text-3xl font-bold text-ink dark:text-ink-inverse sm:text-4xl">
                    Simple, Transparent Pricing
                </h2>
                <p class="font-semibold uppercase tracking-[0.28em] text-accent-500 dark:text-accent-300">
                    We don't get paid until after you get paid
                </p>
            </div>

            <div class="mb-12 flex flex-col items-center gap-3 text-center">
                <div class="max-w-3xl rounded-2xl border border-ink-border/60 px-6 py-4 text-left shadow-sm dark:border-ink-border-dark/60" style="background-color: var(--st-success-container); color: var(--st-on-success-container);">
                    <p class="text-md text-center font-semibold uppercase tracking-[0.28em]">
                        Tips Go Directly To You
                    </p>
                    <p class="mt-2 text-base">
                        We collect no fees for tips you earn through this platform. All money goes directly to you.
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3 max-w-7xl mx-auto items-start">
                @foreach ($planGroups as $group)
                    @php($isPro = $group['key'] === 'pro')
                    @php($isFree = $group['key'] === 'free')

                    <section @class([
                        'flex h-full flex-col rounded-3xl border p-8 shadow-sm transition',
                        'border-brand-600 shadow-brand-900/20 dark:border-brand-300 bg-surface-inverse' => $isPro,
                        'border-ink-border bg-surface-muted dark:border-ink-border/60 dark:bg-surface-inverse' =>
                            !$isPro && !$isFree,
                        'border-ink-border bg-surface dark:border-ink-border-dark dark:bg-surface-elevated' => $isFree,
                    ])>
                        <div class="lg:min-h-[5.75rem]">
                            <h3 @class([
                                'text-2xl font-display font-bold',
                                'text-brand-900' => $isPro,
                                'text-ink dark:text-ink-inverse' => !$isPro,
                            ])>
                                {{ $group['label'] }}
                            </h3>
                            <p @class([
                                'mt-3 text-sm leading-6 min-h-[4.5rem]',
                                'text-brand-50' => $isPro,
                                'text-ink-muted dark:text-ink-soft' => !$isPro,
                            ])>
                                {{ $group['description'] }}
                            </p>
                        </div>

                        <ul class="mt-6 space-y-3">
                            @foreach ($group['features'] as $feature)
                                <li @class([
                                    'flex items-start gap-3 text-sm min-h-[3rem]',
                                    'text-brand-50' => $isPro,
                                    'text-ink-muted dark:text-ink-soft' => !$isPro,
                                ])>
                                    <svg @class([
                                        'mt-0.5 h-5 w-5 shrink-0',
                                        'text-accent-100' => $isPro,
                                        'text-success-500' => !$isPro,
                                    ]) fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>

                        {{-- Free plan: single centered pricing block --}}
                        <div class="mt-8">
                            <div class="flex flex-col items-center rounded-2xl border border-ink-border/60 bg-surface-muted px-5 py-5 dark:border-ink-border-dark/60 dark:bg-surface-inverse">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-ink-muted dark:text-ink-soft">
                                    {{ $group['plans'][0]['interval_label'] }}
                                </p>
                                <p class="mt-3 text-2xl font-bold text-ink dark:text-ink-inverse">
                                    {{ str_replace(['/mo', '/year'], '', $group['plans'][0]['price_label']) }}
                                </p>
                                <p class="mt-2 text-sm font-medium text-ink-muted dark:text-ink-soft">
                                    {{ $group['plans'][0]['subtitle'] }}
                                </p>
                            </div>
                        </div>

                        @if ($planGroups[2]['key'] == $group['key'])
                            <span class="block text-center text-sm font-semibold text-brand-900 mt-8 py-3">
                                Invite Only
                            </span>
                        @else
                            <x-ui.button-link :href="route('register')" class="mt-8 flex w-full px-6 py-3 text-center">
                                Get Started Free
                            </x-ui.button-link>
                        @endif
                    </section>
                @endforeach
            </div>

            <p class="mt-8 text-center text-ink-muted dark:text-ink-soft">
                No credit card at signup. Billing only starts after you've earned $200 in tips through the platform.
            </p>
        </div>
    </section>

    {{-- Migration Section --}}
    <section id="migration" class="relative z-[2] overflow-hidden slant-crop-10 slant-spacing bg-surface dark:bg-surface-inverse">
        <img src="{{ asset('images/AW_ColoradoSprings-07487.webp') }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
        <div class="absolute inset-0 bg-black/65"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-brand-500 dark:text-brand-300">
                    Easy Migration
                </p>
                <h2 class="mt-3 mb-4 font-display text-3xl font-bold text-surface dark:text-ink-inverse sm:text-4xl">
                    Move to Song Tipper in Minutes
                </h2>
                <p class="mx-auto max-w-3xl text-sm text-white/90 dark:text-ink-soft">
                    Bring over your songs, charts, and setlists with bulk import tools, then let AI handle the annoying cleanup work.
                </p>
            </div>

            <div class="max-w-2xl mx-auto">
                <x-ui.card class="border border-white/20 !bg-ink/85 p-8 shadow-2xl backdrop-blur-md">
                    <h3 class="mb-4 text-2xl font-semibold text-white dark:text-ink-inverse">AI Handles the Tedious Parts</h3>
                    <ul class="space-y-3 text-white/90">
                        <li class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-200 dark:text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18l-.813-2.096a2 2 0 00-1.091-1.091L5 14l2.096-.813a2 2 0 001.091-1.091L9 10l.813 2.096a2 2 0 001.091 1.091L13 14l-2.096.813a2 2 0 00-1.091 1.091zM18.259 8.715L18 10l-.259-1.285a1 1 0 00-.544-.544L16 8l1.197-.171a1 1 0 00.544-.544L18 6l.259 1.285a1 1 0 00.544.544L20 8l-1.197.171a1 1 0 00-.544.544zM16 20l-.344-1.032a1 1 0 00-.624-.624L14 18l1.032-.344a1 1 0 00.624-.624L16 16l.344 1.032a1 1 0 00.624.624L18 18l-1.032.344a1 1 0 00-.624.624L16 20z" />
                            </svg>
                            <span>Do literally ZERO data entry for your repertoire. Our AI reads your chart PDFs, requires no guidance, and deduces all of the song's metadata for you with 99% accuracy.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-200 dark:text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>With decent internet speeds, you can upload 200 charts and get to a setup with full metadata enrichment within 10 minutes.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-200 dark:text-surface-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9" stroke-width="2" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h1.5M13.5 10H15M8.5 15.5c1.1-.9 2.27-1.35 3.5-1.35s2.4.45 3.5 1.35M8.5 8.75L10 9.5M14 9.5l1.5-.75" />
                            </svg>
                            <span>We'd rather play music. Nobody wants to spend their time managing files, copying/pasting data, and setting up an app.</span>
                        </li>
                    </ul>

                    <x-ui.button-link :href="route('register')" class="mt-6 inline-flex px-6 py-3 shadow-lg">
                        Start Your Free Migration
                    </x-ui.button-link>
                </x-ui.card>
            </div>
        </div>
    </section>

    {{-- Features Section --}}
    <section class="relative z-[1] bg-surface-muted pt-[calc(5rem+4vw)] pb-[calc(5rem+4vw)] dark:bg-surface-inverse" id="features">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="mb-4 font-display text-3xl font-bold text-ink dark:text-ink-inverse sm:text-4xl">
                    Everything You Need to Perform
                </h2>
                <p class="text-xl text-ink-muted dark:text-ink-soft">
                    One tool for charts, setlists, requests, tips, and your band
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                {{-- Feature 1 --}}
                <x-ui.card class="p-8">
                    <h3 class="mb-3 text-xl font-semibold text-ink dark:text-white">Accept Requests</h3>
                    <p class="text-ink-muted dark:text-ink-soft">
                        Audience members browse your repertoire, request songs, and tip through a clear queue. Set your minimum tip amount and keep the rules easy to follow.
                    </p>
                </x-ui.card>

                {{-- Feature 2 --}}
                <x-ui.card class="p-8">
                    <h3 class="mb-3 text-xl font-semibold text-ink dark:text-white">Real-time Queue</h3>
                    <p class="text-ink-muted dark:text-ink-soft">
                        See requests ranked by tip amount in real-time. Manage your queue from any device and never miss a high-value request.
                    </p>
                </x-ui.card>

                {{-- Feature 3 --}}
                <x-ui.card class="p-8">
                    <h3 class="mb-3 text-xl font-semibold text-ink dark:text-white">Chart Library</h3>
                    <p class="text-ink-muted dark:text-ink-soft">
                        Upload and organize your sheet music. Keep your repertoire organized and accessible during performances.
                    </p>
                </x-ui.card>

                {{-- Feature 4 --}}
                <x-ui.card class="p-8">
                    <h3 class="mb-3 text-xl font-semibold text-ink dark:text-ink-inverse">Smart Setlists</h3>
                    <p class="text-ink-muted dark:text-ink-soft">
                        Create manual setlists or let the system build dynamic ones based on your most requested songs. Always know what to play next.
                    </p>
                </x-ui.card>

                {{-- Feature 5 --}}
                <x-ui.card class="p-8">
                    <h3 class="mb-3 text-xl font-semibold text-ink dark:text-ink-inverse">Gamified Tipping</h3>
                    <p class="text-ink-muted dark:text-ink-soft">
                        Bigger tipping is encouraged through rewards. Offer incentives for tipping more, like song dedications, shoutouts, or even physical rewards at the venue.
                    </p>
                </x-ui.card>

                {{-- Feature 6 --}}
                <x-ui.card class="p-8">
                    <h3 class="mb-3 text-xl font-semibold text-ink dark:text-ink-inverse">Performance Analytics</h3>
                    <p class="text-ink-muted dark:text-ink-soft">
                        See your most popular and lucrative songs. Discover trending songs from other performers worth adding to your repertoire.
                    </p>
                </x-ui.card>

                {{-- Feature 7 --}}
                <x-ui.card class="p-8">
                    <h3 class="mb-3 text-xl font-semibold text-ink dark:text-ink-inverse">Band Sync</h3>
                    <p class="text-ink-muted dark:text-ink-soft">
                        Band members see the same setlist and request queue. Share your repertoire and setlists with your bandmates so everyone is on the same page.
                    </p>
                </x-ui.card>
            </div>
        </div>
    </section>


    {{-- Latest from the Blog --}}
    <section class="relative z-[2] overflow-hidden slant-crop--10 slant-spacing bg-surface dark:bg-surface-inverse">
        <img src="{{ asset('images/AW_Roswell-06802.webp') }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
        <div class="absolute inset-0 bg-black/60"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="mb-4 font-display text-3xl font-bold text-white sm:text-4xl">
                    Latest from the Blog
                </h2>
                <p class="text-xl text-white/70">
                    Tips and strategies for professional musicians
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                @foreach ($latestArticles as $article)
                    <a href="{{ route('blog.show', $article['slug']) }}" class="group">
                        <div class="flex h-full flex-col rounded-2xl border border-white/10 bg-ink/50 p-8 shadow-sm backdrop-blur-sm transition group-hover:border-brand/60 group-hover:bg-white/15 group-hover:shadow-md">
                            <p class="text-sm text-white/60">
                                {{ \Carbon\Carbon::parse($article['published_at'])->format('F j, Y') }}
                            </p>
                            <h3 class="mt-2 font-display text-lg font-bold text-white transition-colors duration-150 ease-in-out group-hover:text-brand">
                                {{ $article['title'] }}
                            </h3>
                            <p class="mt-3 flex-1 text-sm text-white/70">
                                {{ $article['excerpt'] }}
                            </p>
                            <span class="mt-4 inline-flex items-center text-sm font-semibold text-brand">
                                Read More &rarr;
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Find a Performer Section --}}
    <livewire:performer-search lazy />

    {{-- Contact Section --}}
    <section id="contact" class="bg-surface py-20 dark:bg-surface-inverse">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="mb-4 font-display text-3xl font-bold text-ink dark:text-ink-inverse sm:text-4xl">
                    Questions? Get in Touch
                </h2>
                <p class="text-xl text-ink-muted dark:text-ink-soft">
                    We'd love to hear from you
                </p>
            </div>

            <x-ui.card tone="muted" class="p-8">
                <livewire:contact-form lazy />
            </x-ui.card>
        </div>
    </section>

    @include('partials.site-footer')
</x-ui.shell>
