<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-ink dark:text-ink-inverse">
            {{ __('Billing') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-2xl text-sm border border-ink-border/80 bg-surface/95 p-4 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-ink-border/60 p-4 text-sm dark:border-ink-border-dark/60" style="background-color: var(--st-error-container); color: var(--st-on-error-container);">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                <div class="space-y-6">
                    {{-- Subscription Overview --}}
                    <div class="rounded-2xl border border-ink-border/80 bg-surface/95 p-6 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Subscription Overview</h3>
                                <p class="text-sm text-ink-muted dark:text-ink-soft">
                                    We don't get paid until after you get paid. All features are included from day one.
                                </p>
                            </div>
                            <div @class([
                                'rounded-full px-4 py-2 text-center text-xs font-semibold uppercase tracking-[0.2em]',
                                'bg-ink text-surface dark:bg-brand-300 dark:text-surface-inverse' => !$needsPaymentSetup,
                                'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' => $needsPaymentSetup,
                            ])>
                                {{ $billingStatusLabel }}
                            </div>
                        </div>

                        @if ($discountLabel)
                            <div class="mt-6 rounded-xl border border-ink-border/60 p-4 text-sm dark:border-ink-border-dark/60" style="background-color: var(--st-success-container); color: var(--st-on-success-container);">
                                <p class="font-semibold">{{ $discountLabel }}</p>
                                @if ($discountEndsAt)
                                    <p class="mt-1">Your complimentary access runs through {{ $discountEndsAt->toFormattedDateString() }}.</p>
                                @else
                                    <p class="mt-1">This account is permanently exempt from subscription billing.</p>
                                @endif
                            </div>
                        @endif

                        {{-- Earnings Progress (Free users pre-threshold) --}}
                        @if ($user->billing_plan === \App\Models\User::BILLING_PLAN_FREE && !$needsPaymentSetup)
                            <div class="mt-6 space-y-3">
                                <div class="flex items-end justify-between">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.2em] text-ink-muted dark:text-ink-soft">Tips Earned</p>
                                        <p class="mt-1 text-2xl font-bold text-ink dark:text-ink-inverse">${{ number_format($cumulativeTipCents / 100, 2) }}</p>
                                    </div>
                                    <p class="text-sm text-ink-muted dark:text-ink-soft">of ${{ number_format($activationThresholdCents / 100) }} threshold</p>
                                </div>
                                <div class="h-3 w-full overflow-hidden rounded-full bg-surface-muted dark:bg-surface-elevated">
                                    <div class="h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                                </div>
                                <p class="text-xs text-ink-muted dark:text-ink-soft">
                                    At ${{ number_format($activationThresholdCents / 100) }} in cumulative tips, a Pro subscription begins. All features remain free until then.
                                </p>
                            </div>
                        @endif

                        {{-- Grace Period Warning --}}
                        @if ($user->billing_status === \App\Models\User::BILLING_STATUS_GRACE_PERIOD)
                            <div class="mt-6 rounded-xl border border-amber-300/60 p-4 text-sm dark:border-amber-700/60" style="background-color: var(--st-primary-container); color: var(--st-on-primary-container);">
                                <p class="font-semibold">You've earned ${{ number_format($cumulativeTipCents / 100, 2) }} in tips!</p>
                                <p class="mt-1">Choose a Pro plan below to keep receiving audience requests.
                                    @if ($gracePeriodDaysRemaining !== null)
                                        You have <strong>{{ $gracePeriodDaysRemaining }} day{{ $gracePeriodDaysRemaining !== 1 ? 's' : '' }}</strong> remaining.
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Card Needed Warning --}}
                        @if ($user->billing_status === \App\Models\User::BILLING_STATUS_CARD_NEEDED)
                            <div class="mt-6 rounded-xl border border-ink-border/60 p-4 text-sm dark:border-ink-border-dark/60" style="background-color: var(--st-error-container); color: var(--st-on-error-container);">
                                <p class="font-semibold">Audience requests are paused</p>
                                <p class="mt-1">Subscribe to restore audience song requests and tips. All other features remain available.</p>
                            </div>
                        @endif

                        {{-- Active Subscriber Stats --}}
                        @if ($isActiveSubscriber)
                            <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <div class="rounded-xl bg-surface-muted p-4 dark:bg-surface-elevated">
                                    <dt class="text-xs uppercase tracking-[0.2em] text-ink-muted dark:text-ink-soft">Current Plan</dt>
                                    <dd class="mt-2 text-base font-semibold text-ink dark:text-ink-inverse">
                                        {{ $planLabel }}
                                        @if ($isTopEarner)
                                            <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">Top Earner</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="rounded-xl bg-surface-muted p-4 dark:bg-surface-elevated">
                                    <dt class="text-xs uppercase tracking-[0.2em] text-ink-muted dark:text-ink-soft">Price</dt>
                                    <dd class="mt-2 text-base font-semibold text-ink dark:text-ink-inverse">{{ $planPriceLabel ?? 'N/A' }}</dd>
                                </div>
                                <div class="rounded-xl bg-surface-muted p-4 dark:bg-surface-elevated">
                                    <dt class="text-xs uppercase tracking-[0.2em] text-ink-muted dark:text-ink-soft">Lifetime Tips</dt>
                                    <dd class="mt-2 text-base font-semibold text-ink dark:text-ink-inverse">${{ number_format($cumulativeTipCents / 100, 2) }}</dd>
                                </div>
                            </dl>

                            @if ($user->billing_last_error_message)
                                <div class="mt-4 rounded-xl p-4" style="background-color: var(--st-error-container); color: var(--st-on-error-container);">
                                    <dt class="text-xs uppercase tracking-[0.2em]">Billing Error</dt>
                                    <dd class="mt-2 text-sm font-semibold">{{ $user->billing_last_error_message }}</dd>
                                    <dd class="mt-1 text-xs">Please update your payment method below or contact support.</dd>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Manage Billing --}}
                    @if ($isActiveSubscriber)
                        <div class="rounded-2xl border border-ink-border/80 bg-surface/95 p-6 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
                            <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Manage Billing</h3>
                            <div class="mt-3 text-sm text-ink-muted dark:text-ink-soft">
                                <p>
                                    Your <strong class="text-ink dark:text-ink-inverse">subscription</strong> is a recurring fee for the application.
                                    <strong class="text-ink dark:text-ink-inverse">Audience payments</strong> (tips and requests) are deposited directly into your connected Stripe account.
                                </p>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-3">
                                @if ($canOpenPortal)
                                    <x-ui.button-link :href="route('dashboard.billing.portal')" class="px-4 py-2">
                                        Open Stripe Billing Portal
                                    </x-ui.button-link>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Payment Method --}}
                    @if ($canUpdatePaymentMethod)
                        <div class="rounded-2xl border border-ink-border/80 bg-surface/95 p-6 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
                            <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Payment Method</h3>

                            @if ($setupIntentError)
                                @if ($user->pm_type && $user->pm_last_four)
                                    <div class="mt-4 flex items-center justify-between rounded-xl bg-surface-muted p-4 dark:bg-surface-elevated">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.2em] text-ink-muted dark:text-ink-soft">Current method</p>
                                            <p class="mt-1 text-base font-semibold text-ink dark:text-ink-inverse">{{ strtoupper((string) $user->pm_type) }} ending in {{ $user->pm_last_four }}</p>
                                        </div>
                                    </div>
                                @endif
                                <p class="mt-3 text-sm text-accent-700 dark:text-accent-300">{{ $setupIntentError }}</p>
                            @else
                                @if ($user->pm_type && $user->pm_last_four)
                                    <div id="billing-current-pm" class="mt-4 flex items-center justify-between rounded-xl bg-surface-muted p-4 dark:bg-surface-elevated">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.2em] text-ink-muted dark:text-ink-soft">Current method</p>
                                            <p class="mt-1 text-base font-semibold text-ink dark:text-ink-inverse">{{ strtoupper((string) $user->pm_type) }} ending in {{ $user->pm_last_four }}</p>
                                        </div>
                                        <button type="button" id="billing-edit-pm-toggle" class="rounded-lg border border-ink-border px-3 py-1.5 text-sm font-medium text-ink transition hover:bg-surface-muted dark:border-ink-border-dark dark:text-ink-inverse dark:hover:bg-surface-elevated">
                                            Edit
                                        </button>
                                    </div>
                                @endif

                                <div id="billing-payment-method-wrapper" @class(['mt-4', 'hidden' => $user->pm_type && $user->pm_last_four && !$needsPaymentSetup])>
                                    @if ($needsPaymentSetup)
                                        {{-- Activation form: includes plan choice --}}
                                        <form id="billing-payment-method-form" method="POST" action="{{ route('dashboard.billing.activate') }}" class="space-y-4">
                                            @csrf
                                            <div class="space-y-3">
                                                <p class="text-sm font-medium text-ink dark:text-ink-inverse">Choose your plan:</p>
                                                @foreach ($planGroups as $group)
                                                    @if ($group['key'] === 'pro')
                                                        @foreach ($group['plans'] as $plan)
                                                            @php $activationPlanInputId = 'billing-activation-plan-'.$plan['code']; @endphp
                                                            <div>
                                                                <input id="{{ $activationPlanInputId }}" type="radio" name="billing_plan" value="{{ $plan['code'] }}" class="peer sr-only" @checked($plan['badge'] === 'Recommended')>
                                                                <label for="{{ $activationPlanInputId }}" class="block cursor-pointer rounded-xl border border-ink-border p-4 transition hover:border-brand-300 peer-checked:border-brand-600 peer-checked:bg-brand-50 dark:border-ink-border-dark dark:hover:border-brand-300/70 dark:peer-checked:border-brand-300 dark:peer-checked:bg-brand-900/30">
                                                                    <div class="flex items-start justify-between gap-3">
                                                                        <div>
                                                                            <p class="text-base font-semibold text-ink dark:text-ink-inverse">{{ $plan['price_label'] }}</p>
                                                                        </div>
                                                                        @if ($plan['badge'])
                                                                            <span class="rounded-full bg-brand-100 px-2 py-1 text-[11px] font-semibold text-brand-700 dark:bg-brand-900/50 dark:text-brand-200">
                                                                                {{ $plan['badge'] }}
                                                                            </span>
                                                                        @endif
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                @endforeach
                                            </div>
                                            <div id="billing-payment-element" class="rounded-lg border border-ink-border p-4 dark:border-ink-border-dark"></div>
                                            <p id="billing-payment-error" class="hidden text-sm text-red-600 dark:text-red-400"></p>
                                            <input type="hidden" name="payment_method_id" id="billing_payment_method_id" />
                                            <x-primary-button id="billing-payment-submit" type="submit">
                                                Start Subscription
                                            </x-primary-button>
                                        </form>
                                    @else
                                        {{-- Update payment method form --}}
                                        <form id="billing-payment-method-form" method="POST" action="{{ route('dashboard.billing.payment-method') }}" class="space-y-4">
                                            @csrf
                                            <div id="billing-payment-element" class="rounded-lg border border-ink-border p-4 dark:border-ink-border-dark"></div>
                                            <p id="billing-payment-error" class="hidden text-sm text-red-600 dark:text-red-400"></p>
                                            <input type="hidden" name="payment_method_id" id="billing_payment_method_id" />
                                            <div class="flex gap-3">
                                                <x-primary-button id="billing-payment-submit" type="submit">
                                                    Save Payment Method
                                                </x-primary-button>
                                                @if ($user->pm_type && $user->pm_last_four)
                                                    <button type="button" id="billing-cancel-pm-edit" class="rounded-lg border border-ink-border px-4 py-2 text-sm font-medium text-ink transition hover:bg-surface-muted dark:border-ink-border-dark dark:text-ink-inverse dark:hover:bg-surface-elevated">
                                                        Cancel
                                                    </button>
                                                @endif
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Right Sidebar: Change Plan (only for active subscribers) --}}
                @if ($isActiveSubscriber)
                    <div class="rounded-2xl border border-ink-border/80 bg-surface/95 p-6 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
                        <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Change Plan</h3>
                        <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
                            Switch between monthly and yearly billing.
                        </p>

                        <form method="POST" action="{{ route('dashboard.billing.plan') }}" class="mt-5 space-y-4">
                            @csrf

                            @foreach ($planGroups as $group)
                                @if ($group['key'] === 'veteran')
                                    @continue
                                @endif
                                <section class="rounded-2xl border border-ink-border p-4 dark:border-ink-border-dark">
                                    <div class="space-y-1">
                                        <h4 class="text-sm font-semibold text-ink dark:text-ink-inverse">{{ $group['label'] }}</h4>
                                        <p class="text-sm text-ink-muted dark:text-ink-soft">{{ $group['description'] }}</p>
                                    </div>

                                    <div class="mt-4 grid gap-3">
                                        @foreach ($group['plans'] as $plan)
                                            @php $changePlanInputId = 'billing-change-plan-'.$plan['code']; @endphp
                                            <div>
                                                <input id="{{ $changePlanInputId }}" type="radio" name="billing_plan" value="{{ $plan['code'] }}" class="peer sr-only" @checked($user->billing_plan === $plan['code'])>
                                                <label for="{{ $changePlanInputId }}" class="block cursor-pointer rounded-xl border border-ink-border p-4 transition hover:border-brand-300 peer-checked:border-brand-600 peer-checked:bg-brand-50 dark:border-ink-border-dark dark:hover:border-brand-300/70 dark:peer-checked:border-brand-300 dark:peer-checked:bg-brand-900/30">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <p class="text-base font-semibold text-ink dark:text-ink-inverse">{{ $plan['price_label'] }}</p>
                                                        </div>
                                                        @if ($plan['badge'])
                                                            <span class="rounded-full bg-surface px-2 py-1 text-[11px] font-semibold text-accent-700 ring-1 ring-inset ring-accent-100 dark:bg-surface-elevated dark:text-accent-100 dark:ring-accent-900">
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

                            <x-primary-button type="submit">
                                Update Plan
                            </x-primary-button>
                        </form>
                    </div>
                @elseif (!$needsPaymentSetup && !$discountLabel)
                    {{-- Right sidebar for free users: How it works --}}
                    <div class="rounded-2xl border border-ink-border/80 bg-surface/95 p-6 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
                        <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">How Billing Works</h3>
                        <div class="mt-4 space-y-4 text-sm text-ink-muted dark:text-ink-soft">
                            <div class="flex gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700 dark:bg-brand-900/50 dark:text-brand-200">1</span>
                                <p>Use all features for free. No credit card needed.</p>
                            </div>
                            <div class="flex gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700 dark:bg-brand-900/50 dark:text-brand-200">2</span>
                                <p>At ${{ number_format($activationThresholdCents / 100) }} in tips earned, a Pro subscription starts at $19.99/mo or $199.99/year.</p>
                            </div>
                            <div class="flex gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700 dark:bg-brand-900/50 dark:text-brand-200">3</span>
                                <p>Cancel anytime from your Stripe billing portal.</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($canUpdatePaymentMethod && !$setupIntentError)
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            document.addEventListener('DOMContentLoaded', async () => {
                const form = document.getElementById('billing-payment-method-form');
                const submitButton = document.getElementById('billing-payment-submit');
                const paymentMethodInput = document.getElementById('billing_payment_method_id');
                const errorElement = document.getElementById('billing-payment-error');

                if (!form || !submitButton || !paymentMethodInput || !errorElement) {
                    return;
                }

                const stripe = Stripe(@js($stripePublishableKey));
                const prefersDarkMode = typeof window.matchMedia === 'function' &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches;
                const elements = stripe.elements({
                    clientSecret: @js($setupIntentClientSecret),
                    appearance: {
                        theme: prefersDarkMode ? 'night' : 'stripe',
                    },
                });
                const paymentElement = elements.create('payment');
                paymentElement.mount('#billing-payment-element');

                const editToggle = document.getElementById('billing-edit-pm-toggle');
                const cancelEdit = document.getElementById('billing-cancel-pm-edit');
                const pmWrapper = document.getElementById('billing-payment-method-wrapper');

                if (editToggle && pmWrapper) {
                    editToggle.addEventListener('click', () => {
                        pmWrapper.classList.remove('hidden');
                        editToggle.closest('#billing-current-pm')?.classList.add('hidden');
                    });
                }

                if (cancelEdit && pmWrapper) {
                    cancelEdit.addEventListener('click', () => {
                        pmWrapper.classList.add('hidden');
                        document.getElementById('billing-current-pm')?.classList.remove('hidden');
                    });
                }

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

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
            });
        </script>
    @endif
</x-app-layout>
