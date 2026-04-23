<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>End User License Agreement - {{ config('app.name') }}</title>
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
                <h1 class="mb-2 text-3xl font-bold text-ink dark:text-ink-inverse">End User License Agreement</h1>
                <p class="mb-8 text-sm text-ink-muted dark:text-ink-soft">Last updated: {{ now()->format('F j, Y') }}</p>

                <div class="prose dark:prose-invert max-w-none">
                    <div class="mb-8 rounded-lg border border-accent-100 bg-accent-50 p-4 dark:border-accent-900 dark:bg-accent-900/20">
                        <p class="text-sm font-medium text-accent-700 dark:text-accent-200">
                            NOTICE: This is boilerplate legal text. Please review with a qualified attorney before publishing.
                        </p>
                    </div>

                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        This End User License Agreement ("Agreement") is a legal agreement between you ("User") and {{ config('app.name') }} ("Company") for the use of our web application and services ("Software").
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">1. License Grant</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        Subject to the terms of this Agreement, the Company grants you a limited, non-exclusive, non-transferable, revocable license to access and use the Software for your personal or business purposes in accordance with these terms.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">2. Restrictions</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        You agree not to:
                    </p>
                    <ul class="mb-4 list-disc pl-6 text-ink-muted dark:text-ink-soft">
                        <li>Copy, modify, or distribute the Software or any portion thereof</li>
                        <li>Reverse engineer, decompile, or disassemble the Software</li>
                        <li>Rent, lease, lend, sell, or sublicense the Software</li>
                        <li>Remove or alter any proprietary notices or labels</li>
                        <li>Use the Software to develop a competing product or service</li>
                        <li>Use automated systems or software to extract data from the Software</li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">3. Intellectual Property</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        The Software and all copies thereof are proprietary to the Company and title remains with the Company. All rights in the Software not specifically granted in this Agreement are reserved to the Company. The Software is protected by copyright and other intellectual property laws.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">4. User Content</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        You retain all rights to any content you submit, post, or display on or through the Software ("User Content"). By submitting User Content, you grant the Company a worldwide, non-exclusive, royalty-free license to use, reproduce, and display such content in connection with providing the Software.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">5. Third-Party Services</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        The Software may integrate with third-party services (such as payment processors). Your use of such services is subject to their respective terms and privacy policies. The Company is not responsible for any third-party services.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">6. Warranty Disclaimer</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        THE SOFTWARE IS PROVIDED "AS IS" WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NONINFRINGEMENT. THE COMPANY DOES NOT WARRANT THAT THE SOFTWARE WILL BE UNINTERRUPTED OR ERROR-FREE.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">7. Limitation of Liability</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        IN NO EVENT SHALL THE COMPANY BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, OR ANY LOSS OF PROFITS OR REVENUES, WHETHER INCURRED DIRECTLY OR INDIRECTLY, OR ANY LOSS OF DATA, USE, GOODWILL, OR OTHER INTANGIBLE LOSSES.
                    </p>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        THE COMPANY'S TOTAL LIABILITY SHALL NOT EXCEED THE AMOUNT YOU PAID TO THE COMPANY IN THE TWELVE (12) MONTHS PRECEDING THE CLAIM.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">8. Indemnification</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        You agree to indemnify and hold harmless the Company and its officers, directors, employees, and agents from any claims, damages, losses, liabilities, and expenses (including attorneys' fees) arising out of your use of the Software or violation of this Agreement.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">9. Termination</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        This Agreement is effective until terminated. The Company may terminate this Agreement at any time if you fail to comply with any term of this Agreement. Upon termination, you must cease all use of the Software and destroy all copies in your possession.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">10. Governing Law</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        This Agreement shall be governed by and construed in accordance with the laws of the jurisdiction in which the Company is established, without regard to its conflict of law provisions.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">11. Changes to This Agreement</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        The Company reserves the right to modify this Agreement at any time. We will provide notice of any changes by posting the new Agreement on this page and updating the "Last updated" date. Your continued use of the Software after such modifications constitutes acceptance of the modified Agreement.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">12. Contact Information</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        If you have any questions about this Agreement, please contact us through our <x-ui.text-link :href="route('home').'#contact'">contact form</x-ui.text-link>.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-semibold text-ink dark:text-ink-inverse">13. Entire Agreement</h2>
                    <p class="mb-4 text-ink-muted dark:text-ink-soft">
                        This Agreement, together with our Terms of Service and Privacy Policy, constitutes the entire agreement between you and the Company regarding the use of the Software and supersedes all prior agreements and understandings.
                    </p>
                </div>
            </x-ui.card>
        </main>

        @include('partials.site-footer-min')
    </div>
    </x-ui.shell>
</body>
</html>
