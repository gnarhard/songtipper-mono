<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-ink dark:text-ink-inverse">
            {{ __('Complete Billing Setup') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <x-ui.banner class="p-4">
                    {{ session('status') }}
                </x-ui.banner>
            @endif

            @if ($errors->any())
                <x-ui.banner tone="error" class="p-4">
                    <p class="font-semibold">Setup could not be completed.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.banner>
            @endif

            @php
                $initialStep = $errors->has('billing_plan') ? 1 : ($errors->any() ? 2 : 1);
                $selectedPlanDetails = null;

                foreach ($planGroups as $group) {
                    foreach ($group['plans'] as $plan) {
                        if ($plan['code'] !== $selectedPlan) {
                            continue;
                        }

                        $selectedPlanDetails = $plan;

                        break 2;
                    }
                }
            @endphp

            <x-ui.card class="overflow-hidden">
                <div class="border-b border-ink-border px-6 py-5 dark:border-ink-border-dark">
                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Choose Your Performer Plan</h3>
                        <p class="text-sm text-ink-muted dark:text-ink-soft">
                            All paid plans start with a 30-day free trial. Pick the plan that fits your setup now, then continue to checkout.
                        </p>
                    </div>
                </div>

                <div class="space-y-6 px-6 py-6">
                    @if ($discountLabel)
                        <div class="rounded-xl border border-ink-border/60 p-4 text-sm dark:border-ink-border-dark/60"
                            style="background-color: var(--st-success-container); color: var(--st-on-success-container);">
                            <p class="font-semibold">{{ $discountLabel }}</p>
                            <p class="mt-1">
                                This account can finish setup without adding a payment method. Choose Basic or Pro to set your access tier.
                            </p>
                        </div>
                    @endif

                    @if ($requiresPaymentMethod && $setupError)
                        <div class="rounded-lg border border-ink-border/60 p-4 text-sm dark:border-ink-border-dark/60"
                            style="background-color: var(--st-error-container); color: var(--st-on-error-container);">
                            <p class="font-semibold">Stripe setup is currently unavailable.</p>
                            <p class="mt-1">{{ $setupError }}</p>
                            <x-ui.button-link :href="route('setup.billing.show')" variant="secondary" class="mt-3 px-3 py-2 text-xs">
                                Retry Setup Initialization
                            </x-ui.button-link>
                        </div>
                    @else
                        <form id="billing-setup-form" method="POST" action="{{ route('setup.billing.store') }}" class="space-y-6" data-initial-step="{{ $initialStep }}">
                            @csrf

                            <section id="billing-step-plan" class="space-y-6" @if ($initialStep !== 1) hidden @endif>
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-ink-muted dark:text-ink-soft">Step 1 of 2</p>
                                    </div>
                                    <x-primary-button id="billing-plan-continue" type="button">
                                        Continue to Checkout
                                    </x-primary-button>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    @foreach ($planGroups as $group)
                                        <section class="rounded-2xl border border-ink-border p-5 dark:border-ink-border-dark">
                                            <div>
                                                <h4 class="text-base font-semibold text-ink dark:text-ink-inverse">{{ $group['label'] }}</h4>
                                                <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">{{ $group['description'] }}</p>
                                            </div>

                                            <ul class="mt-4 grid gap-2 text-sm text-ink dark:text-ink-inverse">
                                                @foreach ($group['features'] as $feature)
                                                    <li class="flex items-start gap-2">
                                                        <span class="mt-1 h-2 w-2 rounded-full bg-brand-600 dark:bg-brand-300"></span>
                                                        <span>{{ $feature }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>

                                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                                @foreach ($group['plans'] as $plan)
                                                    @php
                                                        $inputId = 'billing-plan-' . $plan['code'];
                                                    @endphp
                                                    <div>
                                                        <input id="{{ $inputId }}" type="radio" name="billing_plan" value="{{ $plan['code'] }}" class="peer sr-only" data-plan-label="{{ $plan['label'] }}" data-plan-price="{{ $plan['price_label'] }}" @checked($selectedPlan === $plan['code'])>
                                                        <label for="{{ $inputId }}" class="block cursor-pointer rounded-xl border border-ink-border bg-surface p-4 transition hover:border-brand-300 dark:border-ink-border-dark dark:bg-surface-inverse dark:hover:border-brand-300/70 peer-checked:border-brand-600 peer-checked:bg-brand-50 dark:peer-checked:border-brand-300 dark:peer-checked:bg-brand-900/25">
                                                            <div class="flex items-start justify-between gap-3">
                                                                <div>
                                                                    <p class="text-sm font-semibold text-ink dark:text-ink-inverse">{{ $plan['label'] }}</p>
                                                                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.2em] text-ink-muted dark:text-ink-soft">{{ $plan['interval_label'] }}</p>
                                                                    <p class="mt-2 text-lg font-semibold text-ink dark:text-ink-inverse">{{ $plan['price_label'] }}</p>
                                                                </div>
                                                                @if ($plan['badge'])
                                                                    <span class="rounded-full bg-surface px-2 py-1 text-[11px] font-semibold text-accent-700 ring-1 ring-inset ring-accent-100 dark:bg-canvas-dark dark:text-accent-100 dark:ring-accent-900">
                                                                        {{ $plan['badge'] }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </section>
                                    @endforeach
                                </div>
                            </section>

                            <section id="billing-step-checkout" class="space-y-6" @if ($initialStep !== 2) hidden @endif>
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-ink-muted dark:text-ink-soft">Step 2 of 2</p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-ink-border p-5 dark:border-ink-border-dark">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <h4 class="text-base font-semibold text-ink dark:text-ink-inverse">Selected plan</h4>
                                            <p id="selected-plan-label" class="mt-1 text-lg font-semibold text-ink dark:text-ink-inverse">
                                                {{ $selectedPlanDetails['label'] ?? 'Choose a plan' }}
                                            </p>
                                            <p id="selected-plan-price" class="text-sm text-ink-muted dark:text-ink-soft">
                                                {{ $selectedPlanDetails['price_label'] ?? '' }}
                                            </p>
                                        </div>
                                        <x-secondary-button id="billing-plan-edit" type="button">
                                            Edit Plan
                                        </x-secondary-button>
                                    </div>
                                </div>

                                @if ($requiresPaymentMethod)
                                    <div class="rounded-2xl border border-ink-border p-5 dark:border-ink-border-dark">
                                        <div class="space-y-2">
                                            <h4 class="text-base font-semibold text-ink dark:text-ink-inverse">Checkout</h4>
                                            <p class="text-sm text-ink-muted dark:text-ink-soft">
                                                You are starting a 30-day free trial for your selected plan. Add a payment method now and Stripe will not charge it until the trial ends.
                                            </p>
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            <div id="payment-element" class="rounded-lg border border-ink-border p-4 dark:border-ink-border-dark"></div>
                                            <p id="stripe-error" class="hidden text-sm text-red-600 dark:text-red-400"></p>
                                        </div>
                                    </div>

                                    <input type="hidden" name="payment_method_id" id="payment_method_id" />
                                @else
                                    <div class="rounded-2xl border border-dashed border-ink-border/60 p-5 text-sm dark:border-ink-border-dark/60"
                                        style="background-color: var(--st-success-container); color: var(--st-on-success-container);">
                                        Payment method collection is skipped because complimentary access is already active for this account.
                                    </div>
                                @endif

                                <div class="rounded-2xl border border-ink-border/60 p-5 text-sm dark:border-ink-border-dark/60"
                                    style="background-color: var(--st-primary-container); color: var(--st-on-primary-container);">
                                    <p class="font-semibold">Stripe Express Connect</p>
                                    <p class="mt-1">
                                        Your money will be managed in Stripe Express Connect. You will be able to access your Connect account in the dashboard.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-3 border-t border-ink-border pt-5 dark:border-ink-border-dark sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-xs text-ink-muted dark:text-ink-soft">
                                        Review your plan, finish setup, and manage your Stripe Express Connect account from the dashboard later.
                                    </p>
                                    <x-primary-button id="billing-setup-submit" type="submit">
                                        {{ $requiresPaymentMethod ? 'Start Free Trial' : 'Enable Complimentary Access' }}
                                    </x-primary-button>
                                </div>
                            </section>
                        </form>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>

    @if ($requiresPaymentMethod && !$setupError)
        <script src="https://js.stripe.com/v3/"></script>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const form = document.getElementById('billing-setup-form');

            if (!form) {
                return;
            }

            const planStep = document.getElementById('billing-step-plan');
            const checkoutStep = document.getElementById('billing-step-checkout');
            const continueButton = document.getElementById('billing-plan-continue');
            const editButton = document.getElementById('billing-plan-edit');
            const planInputs = Array.from(form.querySelectorAll('input[name="billing_plan"]'));
            const selectedPlanLabel = document.getElementById('selected-plan-label');
            const selectedPlanPrice = document.getElementById('selected-plan-price');
            const initialStep = Number(form.dataset.initialStep || '1');
            let currentStep = initialStep;

            const selectedPlanInput = () => planInputs.find((input) => input.checked) ?? null;

            const syncSelectedPlanSummary = () => {
                const input = selectedPlanInput();

                if (!input) {
                    return;
                }

                if (selectedPlanLabel) {
                    selectedPlanLabel.textContent = input.dataset.planLabel || 'Choose a plan';
                }

                if (selectedPlanPrice) {
                    selectedPlanPrice.textContent = input.dataset.planPrice || '';
                }
            };

            const renderStep = () => {
                const showingCheckout = currentStep === 2;

                syncSelectedPlanSummary();

                if (planStep) {
                    planStep.hidden = showingCheckout;
                }

                if (checkoutStep) {
                    checkoutStep.hidden = !showingCheckout;
                }

                if (showingCheckout) {
                    mountPaymentElement();
                }
            };

            continueButton?.addEventListener('click', () => {
                currentStep = 2;
                renderStep();
            });

            editButton?.addEventListener('click', () => {
                currentStep = 1;
                renderStep();
            });

            planInputs.forEach((input) => {
                input.addEventListener('change', syncSelectedPlanSummary);
            });

            let stripe = null;
            let elements = null;
            let paymentElementMounted = false;

            const mountPaymentElement = () => {
                @if ($requiresPaymentMethod && !$setupError)
                    if (paymentElementMounted) {
                        return;
                    }

                    const paymentMethodInput = document.getElementById('payment_method_id');
                    const errorElement = document.getElementById('stripe-error');
                    const paymentElementContainer = document.getElementById('payment-element');

                    if (!paymentMethodInput || !errorElement || !paymentElementContainer || typeof Stripe === 'undefined') {
                        return;
                    }

                    stripe = Stripe(@js($stripePublishableKey));

                    const prefersDarkMode = typeof window.matchMedia === 'function' &&
                        window.matchMedia('(prefers-color-scheme: dark)').matches;

                    elements = stripe.elements({
                        clientSecret: @js($setupIntentClientSecret),
                        appearance: {
                            theme: prefersDarkMode ? 'night' : 'stripe',
                        },
                    });

                    const paymentElement = elements.create('payment', {
                        paymentMethodOrder: ['apple_pay', 'google_pay', 'card', 'cashapp', 'us_bank_account'],
                        layout: {
                            type: 'accordion',
                            visibleAccordionItemsCount: 5,
                        },
                    });

                    paymentElement.mount('#payment-element');
                    paymentElementMounted = true;
                @endif
            };

            renderStep();

            @if ($requiresPaymentMethod && !$setupError)
                const submitButton = document.getElementById('billing-setup-submit');
                const paymentMethodInput = document.getElementById('payment_method_id');
                const errorElement = document.getElementById('stripe-error');

                if (!submitButton || !paymentMethodInput || !errorElement) {
                    return;
                }

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    mountPaymentElement();

                    if (!stripe || !elements) {
                        errorElement.textContent = 'Unable to initialize payment form.';
                        errorElement.classList.remove('hidden');

                        return;
                    }

                    submitButton.disabled = true;
                    submitButton.classList.add('opacity-60', 'cursor-not-allowed');
                    errorElement.classList.add('hidden');
                    errorElement.textContent = '';

                    const {
                        error,
                        setupIntent
                    } = await stripe.confirmSetup({
                        elements,
                        redirect: 'if_required',
                        confirmParams: {
                            return_url: window.location.href,
                        },
                    });

                    if (error || !setupIntent || !setupIntent.payment_method) {
                        const message = error?.message || 'Unable to verify payment method.';
                        errorElement.textContent = message;
                        errorElement.classList.remove('hidden');
                        submitButton.disabled = false;
                        submitButton.classList.remove('opacity-60', 'cursor-not-allowed');

                        return;
                    }

                    paymentMethodInput.value = setupIntent.payment_method;
                    form.submit();
                });
            @endif
        });
    </script>
</x-app-layout>
