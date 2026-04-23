<?php

use App\Models\User;
use App\Services\AccountUsageService;
use App\Services\EmailAccessService;
use App\Support\BillingPlanCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $adminOfferEmail = '';

    public string $adminOfferPlan = User::BILLING_PLAN_PRO_YEARLY;

    public string $adminOfferDiscount = User::BILLING_DISCOUNT_FREE_YEAR;

    public ?string $adminOfferStatusMessage = null;

    #[Computed]
    public function complimentaryOfferPlans(): array
    {
        return app(BillingPlanCatalog::class)->complimentaryOfferPlans();
    }

    #[Computed]
    public function adminUsageDigest(): array
    {
        return app(AccountUsageService::class)->weeklyDigestPayload();
    }

    public function sendBillingOffer(EmailAccessService $emailAccessService): void
    {
        $this->resetValidation();
        $this->adminOfferStatusMessage = null;

        $validated = $this->validate([
            'adminOfferEmail' => ['required', 'string', 'email', 'max:255'],
            'adminOfferPlan' => [
                'required',
                Rule::in([
                    User::BILLING_PLAN_PRO_YEARLY,
                ]),
            ],
            'adminOfferDiscount' => [
                'required',
                Rule::in(User::billingDiscountTypes()),
            ],
        ]);

        try {
            $billingOffer = $emailAccessService->sendBillingOffer(
                email: (string) $validated['adminOfferEmail'],
                billingPlan: (string) $validated['adminOfferPlan'],
                discountType: (string) $validated['adminOfferDiscount'],
            );
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->addError('adminOffer', 'Unable to save and send the complimentary access offer right now. Please retry.');

            return;
        }

        $planLabel = app(BillingPlanCatalog::class)->tierLabel($billingOffer->billing_plan);
        $durationLabel = $this->complimentaryDurationLabel($billingOffer->billing_discount_type);

        $this->adminOfferStatusMessage = "Sent {$durationLabel} {$planLabel} access to {$billingOffer->email}.";
        $this->adminOfferEmail = '';
        $this->adminOfferPlan = User::BILLING_PLAN_PRO_YEARLY;
        $this->adminOfferDiscount = User::BILLING_DISCOUNT_FREE_YEAR;
    }

    private function complimentaryDurationLabel(string $discountType): string
    {
        return $discountType === User::BILLING_DISCOUNT_LIFETIME
            ? 'lifetime'
            : 'one-year';
    }
};
?>

<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-ink-border/80 bg-surface/95 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
            <div class="border-b border-ink-border px-6 py-5 dark:border-ink-border-dark">
                <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Admin Usage Overview</h3>
                <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
                    Quick scaling view for storage, AI spend, bandwidth, and open account flags.
                </p>
            </div>

            <div class="grid gap-4 p-6 md:grid-cols-4">
                <div class="rounded-xl bg-surface-muted p-4 dark:bg-surface-elevated">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Open Flags</p>
                    <p class="mt-2 text-2xl font-bold text-ink dark:text-ink-inverse">{{ count((array) data_get($this->adminUsageDigest, 'open_flags', [])) }}</p>
                </div>
                <div class="rounded-xl bg-surface-muted p-4 dark:bg-surface-elevated">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Storage</p>
                    <p class="mt-2 text-2xl font-bold text-ink dark:text-ink-inverse">{{ number_format(((int) data_get($this->adminUsageDigest, 'margin_risk_summary.current_storage_bytes', 0)) / 1024 / 1024 / 1024, 2) }} GB</p>
                </div>
                <div class="rounded-xl bg-surface-muted p-4 dark:bg-surface-elevated">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">AI Cost</p>
                    <p class="mt-2 text-2xl font-bold text-ink dark:text-ink-inverse">${{ number_format(((int) data_get($this->adminUsageDigest, 'margin_risk_summary.monthly_ai_cost_micros', 0)) / 1000000, 2) }}</p>
                </div>
                <div class="rounded-xl bg-surface-muted p-4 dark:bg-surface-elevated">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Bandwidth</p>
                    <p class="mt-2 text-2xl font-bold text-ink dark:text-ink-inverse">{{ number_format(((int) data_get($this->adminUsageDigest, 'margin_risk_summary.monthly_bandwidth_bytes', 0)) / 1024 / 1024 / 1024, 2) }} GB</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-ink-border/80  bg-surface shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90" data-test="admin-offer-panel">
            <div class="border-b border-ink-border px-6 py-5 dark:border-ink-border-dark">
                <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Complimentary Access Offers</h3>
                <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
                    Email someone complimentary Basic or Pro access. The offer only applies when they sign in or register with the exact recipient email address.
                </p>
            </div>

            <form wire:submit="sendBillingOffer" class="p-6 space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-1">
                        <label for="admin-offer-email" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                            Recipient Email
                        </label>
                        <x-text-input
                            id="admin-offer-email"
                            type="email"
                            wire:model="adminOfferEmail"
                            class="w-full"
                            placeholder="artist@example.com"
                            data-test="admin-offer-email"
                        />
                        @error('adminOfferEmail')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="admin-offer-plan" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                            Plan
                        </label>
                        <x-select-input
                            id="admin-offer-plan"
                            wire:model="adminOfferPlan"
                            class="w-full"
                            data-test="admin-offer-plan"
                        >
                            @foreach ($this->complimentaryOfferPlans as $plan)
                                <option value="{{ $plan['code'] }}">{{ $plan['label'] }}</option>
                            @endforeach
                        </x-select-input>
                        @error('adminOfferPlan')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="admin-offer-duration" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                            Access Length
                        </label>
                        <x-select-input
                            id="admin-offer-duration"
                            wire:model="adminOfferDiscount"
                            class="w-full"
                            data-test="admin-offer-duration"
                        >
                            <option value="{{ \App\Models\User::BILLING_DISCOUNT_FREE_YEAR }}">Free year</option>
                            <option value="{{ \App\Models\User::BILLING_DISCOUNT_LIFETIME }}">Lifetime</option>
                        </x-select-input>
                        @error('adminOfferDiscount')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if ($adminOfferStatusMessage)
                    <div class="rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-700/60 dark:bg-success-900/20 dark:text-success-200" data-test="admin-offer-success">
                        {{ $adminOfferStatusMessage }}
                    </div>
                @endif

                @error('adminOffer')
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/70 dark:bg-red-950/30 dark:text-red-200" data-test="admin-offer-error">
                        {{ $message }}
                    </div>
                @enderror

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <p class="text-sm text-ink-muted dark:text-ink-soft">
                        Existing users are updated immediately. New users get the same complimentary access automatically after they register with that email.
                    </p>
                    <x-primary-button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="sendBillingOffer"
                        class="px-4 py-2.5 disabled:cursor-not-allowed disabled:opacity-60"
                        data-test="send-admin-offer"
                    >
                        <span wire:loading.remove wire:target="sendBillingOffer">Send Offer</span>
                        <span wire:loading wire:target="sendBillingOffer">Sending...</span>
                    </x-primary-button>
                </div>
            </form>
        </div>

        <livewire:app-release-policy-panel />
    </div>
</div>
