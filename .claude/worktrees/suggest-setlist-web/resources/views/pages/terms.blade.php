<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service - {{ config('app.name') }}</title>
    @include('partials.brand-meta')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse">
    <x-ui.shell>
    <div class="min-h-screen">
        <nav class="border-b border-ink-border bg-surface dark:border-ink-border-dark dark:bg-surface-inverse">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('home') }}">
                            <x-application-lockup
                                logo-class="h-9 w-auto"
                                text-class="font-display text-xl font-bold tracking-tight text-ink dark:text-ink-inverse"
                            />
                        </a>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('login') }}" class="text-ink-muted transition hover:text-ink dark:text-ink-soft dark:hover:text-ink-inverse">Login</a>
                        <x-ui.button-link :href="route('register')" class="px-4 py-2">Register</x-ui.button-link>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <x-ui.card class="p-8">
                <h1 class="mb-2 text-3xl font-bold text-ink dark:text-ink-inverse">Terms of Service</h1>
                <p class="mb-8 text-sm text-ink-muted dark:text-ink-soft">Last updated: {{ now()->format('F j, Y') }}</p>

                <div class="prose dark:prose-invert max-w-none">
                    <div class="mb-8 rounded-lg border border-accent-100 bg-accent-50 p-4 dark:border-accent-900 dark:bg-accent-900/20">
                        <p class="text-sm font-medium text-accent-700 dark:text-accent-200">
                            NOTICE: This is boilerplate legal text. Please review with a qualified attorney before publishing.
                        </p>
                    </div>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">1. Acceptance of Terms</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        By accessing and using {{ config('app.name') }} ("Service"), you accept and agree to be bound by the terms and provisions of this agreement. If you do not agree to abide by these terms, please do not use this Service.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">2. Description of Service</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        {{ config('app.name') }} provides a platform for performers to accept song requests with tips from their audience. Performers can manage their request queue, set minimum tip amounts, and organize their repertoire.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">3. User Accounts</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        To use certain features of the Service, you must register for an account. You agree to:
                    </p>
                    <ul class="mb-4 list-disc pl-6 text-ink-muted dark:text-ink-soft">
                        <li>Provide accurate and complete registration information</li>
                        <li>Maintain the security of your password and account</li>
                        <li>Accept responsibility for all activities that occur under your account</li>
                        <li>Notify us immediately of any unauthorized use of your account</li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">4. Fees and Payment</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        Performers must complete billing setup during registration by selecting either a Basic or Pro plan with monthly or yearly billing. Paid subscriptions begin with a 30-day free trial when a valid payment method is provided up front. Complimentary one-year and lifetime discounts may be granted at our discretion and may replace Stripe billing while active. Unless cancelled before renewal, recurring subscriptions renew automatically. All fees are non-refundable except as required by law.
                    </p>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        Basic plans are intended for focused project management and currently include a hard limit of 100 repertoire songs per project, 2 MB chart PDF uploads, a 10 GB storage allocation, and a capped monthly AI enrichment allowance. Pro plans unlock audience requests, queue/history access, analytics, wallet reporting, and higher fair-use thresholds for storage and AI usage.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">5. Fair Use, Quotas, and Abuse Prevention</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We may apply storage limits, AI usage quotas, upload size limits, and rate limits to protect service reliability and maintain sustainable pricing. We may warn you before your account reaches a threshold and may require an upgrade if your usage materially exceeds the limits of your current plan.
                    </p>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        Pro plans are designed to support heavier usage, but they are still subject to fair-use review. We may temporarily restrict uploads, AI enrichment, request flows, or other functionality if we detect unusual spikes, abuse, fraud, or activity that threatens platform stability or profitability.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">6. User Conduct</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        You agree not to:
                    </p>
                    <ul class="mb-4 list-disc pl-6 text-ink-muted dark:text-ink-soft">
                        <li>Use the Service for any unlawful purpose</li>
                        <li>Harass, abuse, or harm other users</li>
                        <li>Interfere with or disrupt the Service</li>
                        <li>Attempt to gain unauthorized access to any portion of the Service</li>
                        <li>Use the Service to transmit spam or malicious content</li>
                    </ul>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        You may not use automated or abusive workflows to overwhelm the Service, harvest data at scale, or generate disproportionate storage, AI, or bandwidth costs relative to your subscribed plan.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">7. Intellectual Property</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        The Service and its original content, features, and functionality are owned by {{ config('app.name') }} and are protected by international copyright, trademark, and other intellectual property laws.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">8. Suspension and Termination</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We may terminate or suspend your account and access to the Service immediately, without prior notice or liability, for any reason, including breach of these Terms. Upon termination, your right to use the Service will cease immediately.
                    </p>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We may also suspend features, place an account under review, or block uploads and AI processing when usage materially exceeds plan limits, creates unusual operational risk, or suggests abuse, fraud, or malicious activity.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">9. Data Archival and Retention</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        To manage storage costs and preserve platform reliability, we may archive derived files after extended inactivity. Our current policy is to warn account owners approximately 30 days before archival and, after roughly 12 months of inactivity, archive derived chart render images while retaining source PDFs and core metadata so they can be regenerated later.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">10. Limitation of Liability</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        In no event shall {{ config('app.name') }}, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential, or punitive damages, including loss of profits, data, or other intangible losses.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">11. Disclaimer</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        The Service is provided on an "AS IS" and "AS AVAILABLE" basis. We make no warranties, expressed or implied, regarding the Service's operation or the information, content, or materials included therein.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">12. Changes to Terms</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We reserve the right to modify or replace these Terms at any time. We will provide notice of any changes by posting the new Terms on this page and updating the "Last updated" date.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">13. Contact Us</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        If you have any questions about these Terms, please contact us through our <x-ui.text-link :href="route('home').'#contact'">contact form</x-ui.text-link>.
                    </p>
                </div>
            </x-ui.card>
        </main>

        @include('partials.site-footer-min')
    </div>
    </x-ui.shell>
</body>
</html>
