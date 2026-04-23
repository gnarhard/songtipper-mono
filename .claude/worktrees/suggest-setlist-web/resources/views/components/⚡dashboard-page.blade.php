<?php

use App\Models\Project;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\AccountUsageService;
use App\Services\PayoutAccountService;
use App\Services\PayoutWalletService;
use App\Support\TipAmount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /** @return Collection<int, Project> */
    #[Computed]
    public function ownedProjects(): Collection
    {
        return Auth::user()->ownedProjects;
    }

    #[Computed]
    public function payoutAccount(): ?UserPayoutAccount
    {
        return Auth::user()->payoutAccount;
    }

    #[Computed]
    public function payoutSetupComplete(): bool
    {
        return $this->payoutAccount?->status === UserPayoutAccount::STATUS_ENABLED;
    }

    #[Computed]
    public function hasProBillingPlan(): bool
    {
        return in_array(Auth::user()->billing_plan, [User::BILLING_PLAN_PRO_MONTHLY, User::BILLING_PLAN_PRO_YEARLY], true);
    }

    #[Computed]
    public function requiresStripeExpressSetupNotice(): bool
    {
        if (!$this->hasProBillingPlan) {
            return false;
        }

        return in_array($this->payoutAccount?->status, [null, UserPayoutAccount::STATUS_NOT_STARTED], true);
    }

    #[Computed]
    public function payoutStatusLabel(): string
    {
        return match ($this->payoutAccount?->status) {
            UserPayoutAccount::STATUS_ENABLED => 'Ready for requests and payouts',
            UserPayoutAccount::STATUS_PENDING => 'Setup in progress',
            UserPayoutAccount::STATUS_RESTRICTED => 'Action required in Stripe',
            default => 'Not started',
        };
    }

    #[Computed]
    public function payoutStatusHelpText(): string
    {
        $statusReason = $this->payoutAccount?->status_reason;

        return match ($statusReason) {
            'requirements_due' => 'Stripe needs additional onboarding details.',
            'requirements_past_due' => 'Stripe has overdue requirements to resolve.',
            'details_not_submitted' => 'Complete Stripe onboarding before requests can be enabled.',
            'capabilities_pending' => 'Stripe is still reviewing account capabilities.',
            'not_started' => 'Connect Stripe Express to collect tips and cash out.',
            null => $this->payoutSetupComplete ? 'Cash out manually from Stripe Express after each gig or set up auto-deposit.' : 'Connect Stripe Express to collect tips and cash out.',
            default => "Stripe account needs attention: {$statusReason}",
        };
    }

    #[Computed]
    public function walletOverview(): array
    {
        $lifetime = app(PayoutWalletService::class)->userLifetimeEarningsSummary(Auth::user());

        $default = [
            'available_total_cents' => 0,
            'pending_total_cents' => 0,
            'gross_tip_amount_cents' => $lifetime['gross_tip_amount_cents'],
            'fee_amount_cents' => $lifetime['fee_amount_cents'],
            'net_tip_amount_cents' => $lifetime['net_tip_amount_cents'],
            'unavailable' => false,
        ];

        $payoutAccount = $this->payoutAccount;
        if (!$this->payoutSetupComplete || !$payoutAccount?->stripe_account_id) {
            return $default;
        }

        try {
            $balance = app(PayoutWalletService::class)->retrieveBalance($payoutAccount->stripe_account_id);
        } catch (\Throwable $throwable) {
            report($throwable);

            return [...$default, 'unavailable' => true];
        }

        return [
            ...$default,
            'available_total_cents' => (int) ($balance['available_total_cents'] ?? 0),
            'pending_total_cents' => (int) ($balance['pending_total_cents'] ?? 0),
        ];
    }

    #[Computed]
    public function currentUsage(): array
    {
        return app(AccountUsageService::class)->usagePayload(Auth::user());
    }

    public function startOrContinuePayoutSetup(PayoutAccountService $payoutAccountService)
    {
        $url = $payoutAccountService->createOnboardingLink(Auth::user());

        return redirect()->away($url);
    }

    public function openStripeExpress(PayoutAccountService $payoutAccountService)
    {
        $url = $this->payoutSetupComplete ? $payoutAccountService->createDashboardLoginLink(Auth::user()) : $payoutAccountService->createOnboardingLink(Auth::user());

        return redirect()->away($url);
    }

    public function refreshStripeConnectStatus(PayoutAccountService $payoutAccountService): void
    {
        $this->resetValidation('payout');

        $user = Auth::user();

        try {
            $payoutAccountService->getForUser($user, refreshFromStripe: true);
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->addError('payout', 'Unable to refresh Stripe status right now. Please try again.');
        }

        $user->unsetRelation('payoutAccount');

        unset($this->payoutAccount);
        unset($this->walletOverview);
    }

    public function formatCents(int $amountCents): string
    {
        return '$' . TipAmount::formatDisplay($amountCents);
    }

    public function formatExactCents(int $amountCents): string
    {
        return '$' . TipAmount::formatExactDisplay($amountCents);
    }
};
?>

<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
        {{-- App Download Banner --}}
        <div class="overflow-hidden rounded-2xl shadow-sm bg-surface dark:bg-surface-inverse border border-ink-border/80 dark:border-ink-border-dark/80">
            <div class="px-6 py-8 sm:px-10 sm:py-10">
                <div class="flex flex-col lg:flex-row items-center gap-8">
                    <div class="flex-1 text-center lg:text-left">
                        <h2 class="text-2xl sm:text-3xl font-bold text-brand mb-3">
                            Manage Everything in the App
                        </h2>
                        <p class="mb-2 text-lg">
                            The {{ config('app.name') }} mobile app is where you'll manage your repertoire, view your queue, and handle requests during performances.
                        </p>
                        <p class="text-sm text-ink-muted dark:text-ink-soft">
                            This web dashboard is for account setup and project creation.
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="#" class="inline-flex items-center justify-center gap-2 rounded-xl border border-ink-border/80 bg-surface px-6 py-3 text-ink shadow-sm transition hover:bg-surface-muted dark:border-ink-border-dark/80 dark:bg-canvas-dark dark:text-ink-inverse dark:hover:bg-surface-elevated group">
                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z" />
                            </svg>
                            <div class="text-left">
                                <div class="text-xs text-ink-muted dark:text-ink-soft">Download on the</div>
                                <div class="text-lg font-semibold -mt-1">App Store</div>
                            </div>
                        </a>
                        <a href="#" class="inline-flex items-center justify-center gap-2 rounded-xl border border-ink-border/80 bg-surface px-6 py-3 text-ink shadow-sm transition hover:bg-surface-muted dark:border-ink-border-dark/80 dark:bg-canvas-dark dark:text-ink-inverse dark:hover:bg-surface-elevated group">
                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                            </svg>
                            <div class="text-left">
                                <div class="text-xs text-ink-muted dark:text-ink-soft">Get it on</div>
                                <div class="text-lg font-semibold -mt-1">Google Play</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-ink-border/80 bg-surface/95 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
            <div class="border-b border-ink-border px-6 py-5 dark:border-ink-border-dark">
                <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Usage and Fair Use</h3>
                <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
                    We offer unlimited usage until you make $200 through the platform. Pro unlocks the audience request stack once you reach that threshold.
                </p>
            </div>

            <div class="grid gap-4 p-6 md:grid-cols-3">
                <div class="rounded-xl border border-brand-200/70 bg-brand-50/80 p-4 dark:border-brand-900/60 dark:bg-brand-900/20">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-700 dark:text-brand-300">Plan</p>
                    <p class="mt-2 text-2xl font-bold text-ink dark:text-ink-inverse">{{ strtoupper((string) data_get($this->currentUsage, 'plan.tier', 'free')) }}</p>
                    <p class="mt-2 text-sm text-ink-muted dark:text-ink-soft">
                        {{ data_get($this->currentUsage, 'plan.code') }}
                    </p>
                </div>

                <div class="rounded-xl border border-ink-border/80 bg-surface-muted p-4 dark:border-ink-border-dark/80 dark:bg-surface-elevated">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Storage</p>
                    <p class="mt-2 text-2xl font-bold text-ink dark:text-ink-inverse">
                        {{ number_format(((int) data_get($this->currentUsage, 'storage.used_bytes', 0)) / 1024 / 1024, 2) }} MB
                    </p>
                    <p class="mt-2 text-sm text-ink-muted dark:text-ink-soft">
                        Status: {{ str_replace('_', ' ', (string) data_get($this->currentUsage, 'storage.status', 'ok')) }}
                    </p>
                </div>

                <div class="rounded-xl border border-ink-border/80 bg-surface-muted p-4 dark:border-ink-border-dark/80 dark:bg-surface-elevated">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">AI Usage</p>
                    <p class="mt-2 text-2xl font-bold text-ink dark:text-ink-inverse">
                        {{ number_format((int) data_get($this->currentUsage, 'ai.operations_used', 0)) }}
                    </p>
                    <p class="mt-2 text-sm text-ink-muted dark:text-ink-soft">
                        Status: {{ str_replace('_', ' ', (string) data_get($this->currentUsage, 'ai.status', 'ok')) }}
                    </p>
                </div>
            </div>

            @if (data_get($this->currentUsage, 'warnings') !== [])
                <div class="border-t border-ink-border px-6 py-4 text-sm text-accent-700 dark:border-ink-border-dark dark:text-accent-300">
                    Active warnings: {{ implode(', ', (array) data_get($this->currentUsage, 'warnings', [])) }}
                </div>
            @endif
        </div>

        {{-- Payout Setup + Wallet --}}
        <div class="rounded-2xl border border-ink-border/80 bg-surface/95 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90" data-test="payout-wallet-card">
            <div class="border-b border-ink-border p-6 dark:border-ink-border-dark">
                <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Payout Setup and Wallet</h3>
                <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
                    Connect Stripe Express once, then cash out manually after gigs.
                </p>
            </div>

            <div class="p-6 space-y-6">
                @if ($this->requiresStripeExpressSetupNotice)
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900 dark:border-red-900 dark:bg-red-950/20 dark:text-red-200" data-test="stripe-express-setup-required">
                        <p class="text-sm font-semibold">Stripe Express Setup Required</p>
                        <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                            Complete Stripe Express setup to enable Pro request collection and payouts.
                        </p>
                    </div>
                @endif

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <p class="text-sm text-ink-muted dark:text-ink-soft">Stripe payout status</p>
                        <p class="text-base font-semibold text-ink dark:text-ink-inverse" data-test="payout-status-label">{{ $this->payoutStatusLabel }}</p>
                        <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft" data-test="payout-status-help">{{ $this->payoutStatusHelpText }}</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <x-primary-button wire:click="startOrContinuePayoutSetup" type="button" data-test="start-payout-setup">
                            {{ $this->payoutSetupComplete ? 'Update Setup' : 'Start Setup' }}
                        </x-primary-button>
                        <x-secondary-button wire:click="openStripeExpress" type="button" data-test="open-stripe-express">
                            Open Stripe Express
                        </x-secondary-button>
                        <x-secondary-button type="button" wire:click="refreshStripeConnectStatus" wire:loading.attr="disabled" wire:target="refreshStripeConnectStatus" class="disabled:cursor-not-allowed disabled:opacity-60" data-test="refresh-stripe-status">
                            <span wire:loading.remove wire:target="refreshStripeConnectStatus">Refresh Status</span>
                            <span wire:loading wire:target="refreshStripeConnectStatus">Refreshing...</span>
                        </x-secondary-button>
                    </div>
                </div>

                @error('payout')
                    <p class="text-sm text-red-600 dark:text-red-300" data-test="payout-error">{{ $message }}</p>
                @enderror

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-lg bg-success-50 p-4 dark:bg-success-900/20">
                        <p class="text-sm text-success-700 dark:text-success-300">Available to Cash Out</p>
                        <p class="text-2xl font-bold text-success-700 dark:text-success-100" data-test="wallet-available">{{ $this->formatCents($this->walletOverview['available_total_cents']) }}</p>
                    </div>
                    <div class="rounded-lg bg-surface-muted p-4 dark:bg-surface-elevated">
                        <p class="text-sm text-ink-muted dark:text-ink-soft">Pending Balance</p>
                        <p class="text-2xl font-bold text-ink dark:text-ink-inverse" data-test="wallet-pending">{{ $this->formatCents($this->walletOverview['pending_total_cents']) }}</p>
                    </div>
                </div>

                <div class="rounded-lg border border-ink-border/70 bg-surface-muted/60 p-4 dark:border-ink-border-dark/70 dark:bg-surface-elevated/60">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Lifetime Tips (All Projects)</p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <p class="text-xs text-ink-muted dark:text-ink-soft">Gross</p>
                            <p class="text-xl font-bold text-ink dark:text-ink-inverse" data-test="wallet-lifetime-gross">{{ $this->formatExactCents($this->walletOverview['gross_tip_amount_cents']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-ink-muted dark:text-ink-soft">Stripe Fees</p>
                            <p class="text-xl font-bold text-ink dark:text-ink-inverse" data-test="wallet-lifetime-fees">−{{ $this->formatExactCents($this->walletOverview['fee_amount_cents']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-success-700 dark:text-success-300">Net Payout</p>
                            <p class="text-xl font-bold text-success-700 dark:text-success-100" data-test="wallet-lifetime-net">{{ $this->formatExactCents($this->walletOverview['net_tip_amount_cents']) }}</p>
                        </div>
                    </div>
                </div>

                @if ($this->walletOverview['unavailable'])
                    <p class="text-sm text-accent-700 dark:text-accent-300" data-test="wallet-unavailable-warning">
                        Stripe wallet data is temporarily unavailable. Retry in a moment.
                    </p>
                @endif
            </div>
        </div>

        {{-- Embeddable Repertoire Widget --}}
        <div class="rounded-2xl border border-ink-border/80 bg-surface/95 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90" data-test="embed-widget-card">
            <div class="border-b border-ink-border px-6 py-5 dark:border-ink-border-dark">
                <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Embeddable Repertoire Widget</h3>
                <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
                    Paste this code into your website to display your full repertoire with search, filters, and sorting.
                </p>
            </div>
            <div class="p-6 space-y-6">
                @foreach ($this->ownedProjects as $project)
                    <div x-data="{ copied: false }">
                        @if ($this->ownedProjects->count() > 1)
                            <label class="mb-2 block text-sm font-medium text-ink dark:text-ink-inverse">{{ $project->name }}</label>
                        @endif
                        @php($embedUrl = route('project.repertoire', ['projectSlug' => $project->slug, 'embed' => 1]))
                        <textarea
                            readonly
                            rows="3"
                            class="w-full rounded-lg border border-ink-border bg-surface-muted px-3 py-2 font-mono text-xs text-ink dark:border-ink-border-dark dark:bg-surface-elevated dark:text-ink-inverse"
                            data-test="embed-code-{{ $project->slug }}"
                        >&lt;iframe src="{{ $embedUrl }}" width="100%" height="600" frameborder="0" style="border:none;border-radius:12px;" title="{{ $project->name }} Repertoire"&gt;&lt;/iframe&gt;</textarea>
                        <div class="mt-2 flex items-center gap-3">
                            <button
                                x-on:click="
                                    navigator.clipboard.writeText($el.closest('div[x-data]').querySelector('textarea').value);
                                    copied = true;
                                    setTimeout(() => copied = false, 2000);
                                "
                                class="inline-flex items-center gap-2 rounded-xl border border-ink-border/80 bg-surface px-4 py-2 text-sm font-medium text-ink shadow-sm transition hover:bg-surface-muted dark:border-ink-border-dark/80 dark:bg-canvas-dark dark:text-ink-inverse dark:hover:bg-surface-elevated"
                                data-test="copy-embed-{{ $project->slug }}"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <span x-text="copied ? 'Copied!' : 'Copy Embed Code'"></span>
                            </button>
                            <a href="{{ route('project.repertoire', ['projectSlug' => $project->slug]) }}" target="_blank" rel="noopener noreferrer" class="text-sm text-brand-600 hover:text-brand-700 dark:text-brand-300 dark:hover:text-brand-200">
                                Preview
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
