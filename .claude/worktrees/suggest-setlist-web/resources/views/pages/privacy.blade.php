<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - {{ config('app.name') }}</title>
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
                <h1 class="mb-2 text-3xl font-bold text-ink dark:text-ink-inverse">Privacy Policy</h1>
                <p class="mb-8 text-sm text-ink-muted dark:text-ink-soft">Last updated: {{ now()->format('F j, Y') }}</p>

                <div class="prose dark:prose-invert max-w-none">
                    <div class="mb-8 rounded-lg border border-accent-100 bg-accent-50 p-4 dark:border-accent-900 dark:bg-accent-900/20">
                        <p class="text-sm font-medium text-accent-700 dark:text-accent-200">
                            NOTICE: This is boilerplate legal text. Please review with a qualified attorney before publishing.
                        </p>
                    </div>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">1. Information We Collect</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We collect information you provide directly to us, including:
                    </p>
                    <ul class="mb-4 list-disc pl-6 text-ink-muted dark:text-ink-soft">
                        <li><strong>Account Information:</strong> Name, email address, and password when you register</li>
                        <li><strong>Profile Information:</strong> Performer name, venue details, and repertoire</li>
                        <li><strong>Payment Information:</strong> Billing details processed through our payment provider</li>
                        <li><strong>Communications:</strong> Messages sent through our contact form</li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">2. Information Collected Automatically</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        When you use our Service, we automatically collect:
                    </p>
                    <ul class="mb-4 list-disc pl-6 text-ink-muted dark:text-ink-soft">
                        <li><strong>Log Data:</strong> IP address, browser type, pages visited, and timestamps</li>
                        <li><strong>Device Information:</strong> Device type, operating system, and unique identifiers</li>
                        <li><strong>Usage Data:</strong> Features used, interactions, performance data, and activity needed to measure storage, bandwidth, and AI usage</li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">3. How We Use Your Information</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We use the information we collect to:
                    </p>
                    <ul class="mb-4 list-disc pl-6 text-ink-muted dark:text-ink-soft">
                        <li>Provide, maintain, and improve our Service</li>
                        <li>Process transactions and send related information</li>
                        <li>Send technical notices, updates, and support messages</li>
                        <li>Respond to your comments, questions, and requests</li>
                        <li>Monitor and analyze trends, usage, and activities</li>
                        <li>Detect, investigate, and prevent fraudulent transactions</li>
                        <li>Calculate plan usage, enforce fair-use controls, and protect service profitability and reliability</li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">4. Information Sharing</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We do not sell your personal information. We may share information with:
                    </p>
                    <ul class="mb-4 list-disc pl-6 text-ink-muted dark:text-ink-soft">
                        <li><strong>Service Providers:</strong> Third parties that perform services on our behalf</li>
                        <li><strong>Legal Requirements:</strong> When required by law or to protect our rights</li>
                        <li><strong>Business Transfers:</strong> In connection with a merger, acquisition, or sale</li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">5. Data Retention</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We retain your personal information for as long as your account is active or as needed to provide you services. We will retain and use your information as necessary to comply with legal obligations, resolve disputes, and enforce our agreements.
                    </p>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We may retain operational records about storage, bandwidth, upload activity, AI usage, and anomaly flags to support billing, abuse prevention, reliability monitoring, and capacity planning. After extended inactivity, we may archive derived chart render images while keeping source files and metadata required to restore the account later.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">6. Data Security</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the Internet is 100% secure.
                    </p>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We may also analyze request volume, upload activity, and related operational signals to detect abuse, protect account data, and prevent events that could jeopardize data durability or platform availability.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">7. Your Rights</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        Depending on your location, you may have the right to:
                    </p>
                    <ul class="mb-4 list-disc pl-6 text-ink-muted dark:text-ink-soft">
                        <li>Access and receive a copy of your personal data</li>
                        <li>Rectify or update inaccurate personal data</li>
                        <li>Request deletion of your personal data</li>
                        <li>Object to or restrict processing of your personal data</li>
                        <li>Data portability</li>
                        <li>Withdraw consent at any time</li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">8. Cookies and Tracking</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We use cookies and similar tracking technologies to track activity on our Service. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">9. Children's Privacy</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        Our Service is not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">10. Changes to This Policy</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last updated" date.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">11. Contact Us</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        If you have any questions about this Privacy Policy, please contact us through our <x-ui.text-link :href="route('home').'#contact'">contact form</x-ui.text-link>.
                    </p>
                </div>
            </x-ui.card>
        </main>

        @include('partials.site-footer-min')
    </div>
    </x-ui.shell>
</body>
</html>
