<?php

use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\PayoutAccountService;
use App\Services\PayoutWalletService;
use App\Support\TipAmount;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
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
        return in_array(Auth::user()->billing_plan, [
            User::BILLING_PLAN_PRO_MONTHLY,
            User::BILLING_PLAN_PRO_YEARLY,
        ], true);
    }

    #[Computed]
    public function requiresStripeExpressSetupNotice(): bool
    {
        if (! $this->hasProBillingPlan) {
            return false;
        }

        return in_array($this->payoutAccount?->status, [
            null,
            UserPayoutAccount::STATUS_NOT_STARTED,
        ], true);
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
            null => $this->payoutSetupComplete
                ? 'Cash out manually from Stripe Express after each gig.'
                : 'Connect Stripe Express to collect tips and cash out.',
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
        if (! $this->payoutSetupComplete || ! $payoutAccount?->stripe_account_id) {
            return $default;
        }

        try {
            $balance = app(PayoutWalletService::class)->retrieveBalance($payoutAccount->stripe_account_id);
        } catch (\Throwable $throwable) {
            report($throwable);

            return [
                ...$default,
                'unavailable' => true,
            ];
        }

        return [
            ...$default,
            'available_total_cents' => (int) ($balance['available_total_cents'] ?? 0),
            'pending_total_cents' => (int) ($balance['pending_total_cents'] ?? 0),
        ];
    }

    public function startOrContinuePayoutSetup(PayoutAccountService $payoutAccountService)
    {
        $url = $payoutAccountService->createOnboardingLink(Auth::user());

        return redirect()->away($url);
    }

    public function openStripeExpress(PayoutAccountService $payoutAccountService)
    {
        $url = $this->payoutSetupComplete
            ? $payoutAccountService->createDashboardLoginLink(Auth::user())
            : $payoutAccountService->createOnboardingLink(Auth::user());

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
        return '$'.TipAmount::formatDisplay($amountCents);
    }

    public function formatExactCents(int $amountCents): string
    {
        return '$'.TipAmount::formatExactDisplay($amountCents);
    }
};
?>

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
                <x-primary-button
                    wire:click="startOrContinuePayoutSetup"
                    type="button"
                    data-test="start-payout-setup"
                >
                    {{ $this->payoutSetupComplete ? 'Update Setup' : 'Start Setup' }}
                </x-primary-button>
                <x-secondary-button
                    wire:click="openStripeExpress"
                    type="button"
                    data-test="open-stripe-express"
                >
                    Open Stripe Express
                </x-secondary-button>
                <x-secondary-button
                    type="button"
                    wire:click="refreshStripeConnectStatus"
                    wire:loading.attr="disabled"
                    wire:target="refreshStripeConnectStatus"
                    class="disabled:cursor-not-allowed disabled:opacity-60"
                    data-test="refresh-stripe-status"
                >
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
            <p class="mt-1 text-xs text-ink-muted dark:text-ink-soft">
                Gross is what your audience paid. Stripe's processing fee (2.9% + $0.30 per tip) is deducted to give you the net payout.
            </p>
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
