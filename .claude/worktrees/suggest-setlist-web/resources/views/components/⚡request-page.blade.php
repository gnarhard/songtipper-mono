<?php

use App\Enums\RequestStatus;
use App\Mail\RequestReceivedMail;
use App\Mail\RewardEarnedMail;
use App\Models\AudienceProfile;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\RewardThreshold;
use App\Models\Song;
use App\Services\AudienceIdentityService;
use App\Services\AudienceRequestPaymentService;
use App\Services\PaymentService;
use App\Services\RewardThresholdService;
use App\Support\TipAmount;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Project $project;
    public Song $song;

    public int $tipAmountCents = 2000;
    public ?int $customTipCents = null;
    public string $note = '';

    public ?string $clientSecret = null;
    public ?int $requestId = null;
    public ?string $error = null;
    public ?string $tipAmountError = null;
    public bool $isOriginalRequest = false;
    public bool $isTipOnly = false;
    public bool $isCustomTip = false;
    public string $audienceVisitorToken = '';

    public function mount(string $projectSlug, int $songId): void
    {
        $this->project = Project::query()
            ->where('slug', $projectSlug)
            ->with(['owner.payoutAccount'])
            ->firstOrFail();

        $this->song = Song::findOrFail($songId);
        $this->isOriginalRequest = Song::isOriginalRequestSong($this->song);
        $this->isTipOnly = Song::isTipJarSupportSong($this->song);
        $presetTipAmounts = $this->presetTipAmounts();
        $this->tipAmountCents = $this->project->is_accepting_tips
            ? ($presetTipAmounts[0] ?? $this->minimumTipCents())
            : 0;
        $this->isCustomTip = $this->project->is_accepting_tips && !$this->isPresetTipAmount($this->tipAmountCents);
        $audienceIdentityService = app(AudienceIdentityService::class);
        $this->audienceVisitorToken = $audienceIdentityService->ensureVisitorToken(request());

        if ($this->isOriginalRequest && !$this->isTipOnly && !$this->project->is_accepting_original_requests) {
            $this->error = 'This project is not currently accepting original requests.';
            return;
        }

        if ($this->isTipOnly && !$this->project->is_accepting_tips) {
            $this->error = 'This project is not currently accepting tips.';
            return;
        }

        if (!$this->project->is_accepting_tips) {
            return;
        }

        $this->createPaymentIntent();
    }

    public function setTip(int $cents, bool $fromCustom = false): void
    {
        if (!$this->project->is_accepting_tips) {
            $this->tipAmountCents = 0;
            $this->isCustomTip = false;
            $this->clientSecret = null;
            $this->tipAmountError = null;
            return;
        }

        $this->tipAmountCents = TipAmount::normalizeCents(max($cents, 0));
        $this->isCustomTip = $fromCustom;
        $this->tipAmountError = null;

        if ($fromCustom && $this->tipAmountCents > 0) {
            $this->customTipCents = $this->tipAmountCents;
        }

        if ($this->tipAmountCents === 0 && $this->minimumTipCents() === 0) {
            $this->clientSecret = null;
            return;
        }

        if ($this->tipAmountCents < $this->minimumTipCents()) {
            $this->tipAmountError = 'Tips must be at least $' . TipAmount::formatDisplay($this->minimumTipCents()) . '.';
            $this->clientSecret = null;
            return;
        }

        if ($this->tipAmountCents > 0 && $this->tipAmountCents < 50) {
            $this->tipAmountError = 'That amount is too low to be accepted by Stripe.';
            $this->clientSecret = null;
            return;
        }

        if ($this->clientSecret !== null) {
            $this->updateExistingPaymentIntent();
        } else {
            $this->createPaymentIntent();
        }
    }

    public function setCustomTipMode(): void
    {
        if (!$this->project->is_accepting_tips) {
            return;
        }

        $this->isCustomTip = true;

        if ($this->customTipCents !== null && $this->customTipCents !== $this->tipAmountCents) {
            $this->setTip($this->customTipCents, true);
        }
    }

    private function updateExistingPaymentIntent(): void
    {
        $paymentIntentId = explode('_secret_', $this->clientSecret)[0] ?? '';
        $stripeAccountId = trim((string) ($this->project->owner?->payoutAccount?->stripe_account_id ?? ''));

        if ($paymentIntentId === '' || $stripeAccountId === '') {
            $this->createPaymentIntent();
            return;
        }

        $paymentService = app(PaymentService::class);
        try {
            $paymentService->updatePaymentIntentAmount(
                $paymentIntentId,
                $stripeAccountId,
                $this->tipAmountCents,
            );
            $this->dispatch('stripe-payment-intent-updated');
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->clientSecret = null;
            $this->createPaymentIntent();
        }
    }

    #[Computed]
    public function highestActiveQueueTipCents(): int
    {
        return max((int) ($this->project->highest_active_tip ?? 0), 0);
    }

    #[Computed]
    public function queueRulesExplainer(): string
    {
        return 'Requests and tips go directly to the performer and show up on their device. Higher tips move songs up the queue.';
    }

    #[Computed]
    public function visitorHasHighestActiveTip(): bool
    {
        $highestActiveTipCents = $this->highestActiveQueueTipCents;

        if ($highestActiveTipCents <= 0) {
            return false;
        }

        $audienceProfileId = app(AudienceIdentityService::class)
            ->findProfile($this->project, $this->audienceVisitorToken)
            ?->id;

        if ($audienceProfileId === null) {
            return false;
        }

        return SongRequest::query()
            ->where('project_id', $this->project->id)
            ->where('status', RequestStatus::Active)
            ->where('audience_profile_id', $audienceProfileId)
            ->where('tip_amount_cents', $highestActiveTipCents)
            ->exists();
    }

    #[Computed]
    public function queuePriorityGuidance(): ?string
    {
        if (
            ! $this->project->is_accepting_requests
            || ! $this->project->is_accepting_tips
            || $this->isTipOnly
            || $this->visitorHasHighestActiveTip
        ) {
            return null;
        }

        if ($this->tipAmountCents > $this->highestActiveQueueTipCents) {
            return 'This tip would put your request at #1.';
        }

        $additionalCents = max(
            TipAmount::nextHigherDollarAmount($this->highestActiveQueueTipCents) - $this->tipAmountCents,
            100
        );

        return 'Add $' . TipAmount::formatDisplay($additionalCents) . ' more to take #1 in the queue.';
    }

    #[Computed]
    public function audienceProfile(): ?AudienceProfile
    {
        return app(AudienceIdentityService::class)
            ->findProfile($this->project, $this->audienceVisitorToken);
    }

    #[Computed]
    public function audienceCumulativeTipCents(): int
    {
        return $this->audienceProfile?->cumulative_tip_cents ?? 0;
    }

    #[Computed]
    public function claimableFreeRequestThreshold(): ?RewardThreshold
    {
        $profile = $this->audienceProfile;
        if ($profile === null) {
            return null;
        }

        $this->project->loadMissing('rewardThresholds');
        $service = app(RewardThresholdService::class);

        return $this->project->rewardThresholds
            ->filter(fn (RewardThreshold $t) => $t->reward_type === RewardThreshold::TYPE_FREE_REQUEST)
            ->first(fn (RewardThreshold $t) => $service->hasClaimableReward($profile, $t));
    }

    #[Computed]
    public function hasEarnedFreeRequest(): bool
    {
        return $this->claimableFreeRequestThreshold !== null;
    }

    #[Computed]
    public function rewardProgressMessages(): array
    {
        $profile = $this->audienceProfile;
        if ($profile === null || $this->audienceCumulativeTipCents <= 0) {
            return [];
        }

        $this->project->loadMissing('rewardThresholds');
        $service = app(RewardThresholdService::class);
        $messages = [];

        foreach ($this->project->rewardThresholds as $threshold) {
            $remaining = $service->centsUntilNextClaim($profile, $threshold);
            if ($remaining > 0) {
                $messages[] = "You're $" . TipAmount::formatDisplay($remaining) . ' away from: ' . $threshold->reward_label . '!';
            }
        }

        return $messages;
    }

    /**
     * @deprecated Use rewardProgressMessages instead. Kept for backward compat.
     */
    #[Computed]
    public function freeRequestProgressMessage(): ?string
    {
        return $this->rewardProgressMessages[0] ?? null;
    }

    public function submitFreeRequest(): void
    {
        $this->error = null;

        if (!$this->project->is_accepting_requests) {
            $this->error = 'This project is not currently accepting requests.';
            return;
        }

        if ($this->isTipOnly) {
            $this->error = 'Free requests cannot be used for tip-only submissions.';
            return;
        }

        if ($this->isOriginalRequest && !$this->project->is_accepting_original_requests) {
            $this->error = 'This project is not currently accepting original requests.';
            return;
        }

        $rewardThreshold = $this->claimableFreeRequestThreshold;
        if ($rewardThreshold === null) {
            $this->error = 'You have not yet earned a free request.';
            return;
        }

        $audienceIdentityService = app(AudienceIdentityService::class);
        $audienceProfile = $audienceIdentityService->resolveProfile(
            project: $this->project,
            visitorToken: $this->audienceVisitorToken,
            ipAddress: request()->ip(),
        );

        $rewardService = app(RewardThresholdService::class);
        $claim = $rewardService->claimReward($audienceProfile, $rewardThreshold);

        if ($claim === null) {
            $this->error = 'You have not yet earned a free request.';
            return;
        }

        $this->validate();
        $note = $this->normalizedNote();

        $highestActiveTip = (int) SongRequest::query()
            ->where('project_id', $this->project->id)
            ->where('status', RequestStatus::Active)
            ->max('score_cents');

        $songRequest = SongRequest::create([
            'project_id' => $this->project->id,
            'audience_profile_id' => $audienceProfile->id,
            'song_id' => $this->song->id,
            'tip_amount_cents' => 0,
            'score_cents' => $highestActiveTip + 100,
            'status' => RequestStatus::Active,
            'note' => $note,
            'requested_from_ip' => request()->ip(),
            'payment_provider' => 'awarded',
            'played_at' => null,
        ]);

        $this->requestId = $songRequest->id;
        $this->sendNotification($songRequest, $audienceProfile);
        $this->sendRewardNotification($rewardThreshold, $audienceProfile);

        $audienceRequestPaymentService = app(AudienceRequestPaymentService::class);
        $queuePosition = $audienceRequestPaymentService->queuePosition($songRequest);

        \App\Jobs\ResolveRequestLocation::dispatch($songRequest->id);

        session()->flash('request_success', [
            'message' => $audienceRequestPaymentService->queuePositionMessage($queuePosition),
            'queue_position' => $queuePosition,
            'request_id' => $songRequest->id,
        ]);

        $this->redirect(
            route('project.page', ['projectSlug' => $this->project->slug]),
            navigate: true,
        );
    }

    #[Computed]
    public function presetTipAmounts(): array
    {
        if (!$this->project->is_accepting_tips) {
            return [];
        }

        $minimumTipCents = $this->minimumTipCents();

        return array_values(array_filter(
            $this->project->quick_tip_amounts_cents,
            fn(int $amount): bool => $amount >= $minimumTipCents
        ));
    }

    private function isPresetTipAmount(int $tipAmountCents): bool
    {
        return in_array($tipAmountCents, $this->project->quick_tip_amounts_cents, true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'note.max' => 'Keep your message under 500 characters.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'note' => 'message',
        ];
    }

    public function createPaymentIntent(): void
    {
        $this->error = null;
        $this->tipAmountError = null;
        $this->requestId = null;
        $this->clientSecret = null;

        if (!$this->project->is_accepting_requests) {
            $this->error = 'This project is not currently accepting requests.';
            return;
        }

        if ($this->isOriginalRequest && !$this->isTipOnly && !$this->project->is_accepting_original_requests) {
            $this->error = 'This project is not currently accepting original requests.';
            return;
        }

        if ($this->isTipOnly && !$this->project->is_accepting_tips) {
            $this->error = 'This project is not currently accepting tips.';
            return;
        }

        $this->validate();
        $note = $this->normalizedNote();

        if (!$this->project->is_accepting_tips) {
            $audienceIdentityService = app(AudienceIdentityService::class);
            $audienceProfile = $audienceIdentityService->resolveProfile(project: $this->project, visitorToken: $this->audienceVisitorToken, ipAddress: request()->ip());

            $songRequest = SongRequest::create([
                'project_id' => $this->project->id,
                'audience_profile_id' => $audienceProfile->id,
                'song_id' => $this->song->id,
                'tip_amount_cents' => 0,
                'score_cents' => 0,
                'status' => RequestStatus::Active,
                'note' => $note,
                'requested_from_ip' => request()->ip(),
                'payment_provider' => 'none',
                'played_at' => null,
            ]);

            $this->requestId = $songRequest->id;
            $this->sendNotification($songRequest, $audienceProfile);
            $this->redirect(
                route('request.confirmation', [
                    'redirect_status' => 'succeeded',
                    'project_slug' => $this->project->slug,
                    'submission' => 'request',
                ]),
                navigate: true,
            );

            return;
        }

        if ($this->tipAmountCents === 0 && $this->minimumTipCents() === 0) {
            $audienceIdentityService = app(AudienceIdentityService::class);
            $audienceProfile = $audienceIdentityService->resolveProfile(project: $this->project, visitorToken: $this->audienceVisitorToken, ipAddress: request()->ip());

            $songRequest = SongRequest::create([
                'project_id' => $this->project->id,
                'audience_profile_id' => $audienceProfile->id,
                'song_id' => $this->song->id,
                'tip_amount_cents' => 0,
                'score_cents' => 0,
                'status' => $this->isTipOnly ? RequestStatus::Played : RequestStatus::Active,
                'note' => $note,
                'requested_from_ip' => request()->ip(),
                'payment_provider' => 'none',
                'played_at' => $this->isTipOnly ? now() : null,
            ]);

            $this->requestId = $songRequest->id;
            $this->sendNotification($songRequest, $audienceProfile);

            if (!$this->isTipOnly) {
                $audienceRequestPaymentService = app(AudienceRequestPaymentService::class);
                $queuePosition = $audienceRequestPaymentService->queuePosition($songRequest);

                session()->flash('request_success', [
                    'message' => $audienceRequestPaymentService->queuePositionMessage($queuePosition),
                    'queue_position' => $queuePosition,
                    'request_id' => $songRequest->id,
                ]);

                $this->redirect(
                    route('project.page', [
                        'projectSlug' => $this->project->slug,
                    ]),
                    navigate: true,
                );

                return;
            }

            $this->redirect(
                route('request.confirmation', [
                    'redirect_status' => 'succeeded',
                    'project_slug' => $this->project->slug,
                    'submission' => $this->isTipOnly ? 'tip' : 'request',
                ]),
                navigate: true,
            );

            return;
        }

        $stripeAccountId = trim((string) ($this->project->owner?->payoutAccount?->stripe_account_id ?? ''));
        if ($stripeAccountId === '') {
            $this->error = 'This project is not currently accepting requests.';
            return;
        }

        $paymentService = app(PaymentService::class);
        try {
            $paymentIntent = $paymentService->createPaymentIntent($this->project, $stripeAccountId, $this->tipAmountCents, [
                'song_id' => $this->song->id,
                'note' => $note,
                'requested_from_ip' => request()->ip(),
                'visitor_token' => $this->audienceVisitorToken,
                'tip_only' => $this->isTipOnly ? '1' : '0',
                'request_type' => $this->isTipOnly ? 'Tip Only' : (Song::isOriginalRequestSong($this->song) ? 'Original Song Request' : 'Song Request'),
                'song_title' => $this->song->title,
                'song_artist' => $this->song->artist,
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->clientSecret = null;
            $this->error = 'Unable to initialize payment right now. Please try again.';
            return;
        }

        $this->clientSecret = $paymentIntent->client_secret;
        $this->dispatch('stripe-payment-intent-updated');
    }

    public function syncNoteToPaymentIntent(): void
    {
        if ($this->clientSecret === null) {
            return;
        }

        $paymentIntentId = explode('_secret_', $this->clientSecret)[0] ?? '';
        $stripeAccountId = trim((string) ($this->project->owner?->payoutAccount?->stripe_account_id ?? ''));

        if ($paymentIntentId === '' || $stripeAccountId === '') {
            return;
        }

        $note = $this->normalizedNote();

        app(PaymentService::class)->updatePaymentIntentMetadata(
            $paymentIntentId,
            $stripeAccountId,
            ['note' => $note ?? ''],
        );
    }

    public function minimumTipCents(): int
    {
        return TipAmount::normalizeCents($this->project->min_tip_cents);
    }

    private function normalizedNote(): ?string
    {
        $trimmedNote = trim($this->note);

        if ($trimmedNote === '') {
            return null;
        }

        return $trimmedNote;
    }

    private function sendNotification(SongRequest $songRequest, AudienceProfile $audienceProfile): void
    {
        $this->project->loadMissing('owner');

        if (! $this->project->notify_on_request || ! $this->project->owner?->email) {
            return;
        }

        $songRequest->setRelation('project', $this->project);
        $songRequest->setRelation('song', $this->song);
        $songRequest->setRelation('audienceProfile', $audienceProfile);

        Mail::to($this->project->owner->email)->queue(new RequestReceivedMail($songRequest));
    }

    private function sendRewardNotification(RewardThreshold $rewardThreshold, AudienceProfile $audienceProfile): void
    {
        $this->project->loadMissing('owner');

        if (! $this->project->notify_on_request || ! $this->project->owner?->email) {
            return;
        }

        $rewardThreshold->setRelation('project', $this->project);

        Mail::to($this->project->owner->email)->queue(new RewardEarnedMail($rewardThreshold, $audienceProfile));
    }
};
?>

<x-ui.shell>
    <header class="border-b border-ink-border bg-surface/95 shadow-sm dark:border-ink-border-dark dark:bg-surface-inverse/95">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-2">
            <a href="{{ route('project.page', ['projectSlug' => $project->slug]) }}" class="text-sm font-medium text-ink transition hover:text-ink-muted dark:text-ink-inverse dark:hover:text-ink-soft">
                &larr; Back to repertoire
            </a>

            @if ($isTipOnly)
                <h1 class="text-3xl font-bold text-ink dark:text-ink-inverse mt-4 text-center">
                    Tip
                </h1>
            @else
                <h1 class="text-3xl font-bold text-ink dark:text-ink-inverse mt-4 text-center">
                    Request
                </h1>
            @endif
            <div class="2">
                @if ($isTipOnly)
                    <p class="mt-2 text-sm text-ink-muted/80 dark:text-ink-soft/90 text-center">
                        without adding a song to the queue
                    </p>
                @elseif ($isOriginalRequest)
                    <p class="mt-2 text-sm text-ink-muted/80 dark:text-ink-soft/90 text-center">
                        an original from {{ $project->name }}
                    </p>
                @else
                    <p class="mt-2 text-sm text-ink-muted/80 dark:text-ink-soft/90 text-center">{{ $isTipOnly ? 'Leave a Tip' : ($isOriginalRequest ? 'Request an Original' : '"' . $song->title . '"') }} by {{ $song->artist }}</p>
                @endif
            </div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <x-ui.panel class="overflow-hidden">
            <div class="p-4">
                @if ($error)
                    <x-ui.banner tone="error" class="mb-6 p-4">
                        <p class="text-sm">{{ $error }}</p>
                    </x-ui.banner>
                @endif

                @if (!$isTipOnly && $this->hasEarnedFreeRequest)
                    <div class="space-y-4">
                        <p class="text-sm font-medium text-ink dark:text-ink-inverse text-center">
                            You've earned a free request!
                        </p>
                        <x-textarea-input id="free-note" wire:model="note" rows="1" maxlength="500" placeholder="Message (optional)" class="block w-full resize-none placeholder:text-ink-muted/70 dark:placeholder:text-ink-soft/60 sm:text-sm"></x-textarea-input>
                        <x-primary-button wire:click="submitFreeRequest" wire:loading.attr="disabled" class="w-full px-4 py-3 text-base disabled:opacity-50">
                            <span wire:loading.remove wire:target="submitFreeRequest">Claim Free Request</span>
                            <span wire:loading wire:target="submitFreeRequest">Processing...</span>
                        </x-primary-button>
                    </div>
                @else

                <div class="space-y-4" x-data="{ showCustomAmount: @js($project->is_accepting_tips && $isCustomTip) }">
                    @if ($project->is_accepting_tips)
                        <div>
                            <label class="mb-3 block text-ls text-center font-medium text-ink dark:text-ink-inverse">Choose Tip</label>
                            @if ($this->queuePriorityGuidance)
                                <x-ui.banner class="my-3 p-3">
                                    <p class="text-sm text-success-700 dark:text-success-200">{{ $this->queuePriorityGuidance }}</p>
                                </x-ui.banner>
                            @endif
                            <div class="grid grid-cols-4 gap-1">
                                @foreach ($this->presetTipAmounts as $amount)
                                    <button wire:click="setTip({{ $amount }})" x-on:click="showCustomAmount = false; const btn = document.getElementById('submit-payment'); if (btn) btn.textContent = 'Tip ${{ number_format($amount / 100, 0) }}'" @class([
                                        'rounded-lg border px-2 py-3 text-sm font-medium',
                                        'border-action-500 bg-action-500 text-ink hover:bg-brand-50 dark:border-action-500 dark:bg-action-500 dark:text-ink dark:hover:bg-action-100' =>
                                            !$isCustomTip && $tipAmountCents === $amount,
                                        'border-ink-border bg-surface text-ink hover:bg-brand-50 dark:border-ink-border-dark dark:bg-canvas-dark dark:text-ink-inverse dark:hover:bg-surface-elevated' =>
                                            $isCustomTip || $tipAmountCents !== $amount,
                                    ])>
                                        ${{ number_format($amount / 100, 0) }}
                                    </button>
                                @endforeach
                                <button type="button" wire:click="setCustomTipMode" x-on:click="showCustomAmount = true; $nextTick(() => $refs.customTipInput?.focus()); const rawValue = parseFloat($refs.customTipInput?.value || 0); if (Number.isFinite(rawValue) && rawValue > 0) { const btn = document.getElementById('submit-payment'); if (btn) btn.textContent = 'Tip $' + Math.ceil(rawValue); }" @class([
                                    'rounded-lg border px-2 py-3 text-sm font-medium',
                                    'border-action-500 bg-action-500 text-ink hover:bg-brand-50 dark:border-action-500 dark:bg-action-500 dark:text-ink dark:hover:bg-action-100' => $isCustomTip,
                                    'border-ink-border bg-surface text-ink hover:bg-brand-50 dark:border-ink-border-dark dark:bg-canvas-dark dark:text-ink-inverse dark:hover:bg-surface-elevated' => !$isCustomTip,
                                ])>
                                    Custom
                                </button>
                            </div>
                            @if ($tipAmountError)
                                <p class="mt-3 text-sm text-danger-600 dark:text-danger-300">
                                    {{ $tipAmountError }}
                                </p>
                            @endif
                            @foreach ($this->rewardProgressMessages as $progressMessage)
                                <p class="mt-3 text-sm text-accent-600 dark:text-accent-300 text-center">
                                    {{ $progressMessage }}
                                </p>
                            @endforeach
                        </div>

                        <div x-show="showCustomAmount" x-transition.opacity.duration.150ms x-cloak>
                            <label for="customTip" class="sr-only">Custom Amount</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-ink-muted dark:text-ink-soft sm:text-sm">$</span>
                                </div>
                                <x-text-input type="number" id="customTip" min="0" step="1" placeholder="Enter amount" class="placeholder:text-ink-muted/70 dark:placeholder:text-ink-soft/60 block w-full pl-7 sm:text-sm" x-data x-ref="customTipInput" @input.debounce.500ms="$wire.setTip((() => { const amount = parseFloat($event.target.value || 0); if (!Number.isFinite(amount) || amount <= 0) { return 0; } return Math.ceil(amount) * 100; })(), true)" />
                            </div>
                        </div>
                    @elseif (!$isTipOnly)
                        <div class="rounded-lg border border-accent-100 bg-accent-50 p-4 dark:border-accent-900 dark:bg-accent-900/30">
                            <p class="text-sm text-accent-700 dark:text-accent-100">
                                Tips are turned off for this event. You can still submit a request for free.
                            </p>
                        </div>
                    @endif

                    <div>
                        <x-textarea-input id="note" wire:model="note" rows="1" maxlength="500" placeholder="Message (optional)" class="block w-full resize-none placeholder:text-ink-muted/70 dark:placeholder:text-ink-soft/60 sm:text-sm"></x-textarea-input>
                        @error('note')
                            <p class="mt-2 text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ((!$project->is_accepting_tips && !$isTipOnly) || ($tipAmountCents === 0 && $this->minimumTipCents() === 0))
                        <div class="pt-4">
                            <x-primary-button wire:click="createPaymentIntent" wire:loading.attr="disabled" class="w-full px-4 py-3 text-base disabled:opacity-50">
                                <span wire:loading.remove wire:target="createPaymentIntent">
                                    {{ $isTipOnly ? 'Send Tip' : 'Submit Request' }}
                                </span>
                                <span wire:loading wire:target="createPaymentIntent">
                                    Processing...
                                </span>
                            </x-primary-button>
                        </div>
                    @endif
                </div>

                @if ($clientSecret)
                    <div class="mt-4 border-t border-ink-border pt-4 dark:border-ink-border-dark">
                        <div id="payment-form" data-stripe-key="{{ config('services.stripe.key') }}" data-stripe-account="{{ $project->owner?->payoutAccount?->stripe_account_id }}" data-client-secret="{{ $clientSecret }}" data-return-url="{{ route('request.confirmation', [
                            'project_slug' => $project->slug,
                            'submission' => $isTipOnly ? 'tip' : 'request',
                        ]) }}" data-pay-label="Tip ${{ TipAmount::formatDisplay($tipAmountCents) }}">
                            <div id="payment-element" wire:ignore class="mb-6"></div>
                            <x-primary-button id="submit-payment" type="button" class="w-full px-4 py-3 text-base disabled:opacity-50">
                                Tip ${{ TipAmount::formatDisplay($tipAmountCents) }}
                            </x-primary-button>
                            <div id="payment-message" class="mt-4 hidden text-sm text-danger-600 dark:text-danger-300"></div>
                        </div>
                    </div>
                @endif

                @endif
            </div>
        </x-ui.panel>
    </main>
</x-ui.shell>

@push('head')
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
@endpush

@once
    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            window.songtipperRequestPageStripe = window.songtipperRequestPageStripe || {
                isListening: false,
                stripe: null,
                elements: null,
                currentClientSecret: null,
                currentStripeAccountId: null,
            };

            const stripeState = window.songtipperRequestPageStripe;

            const mountStripePaymentElement = () => {
                const paymentForm = document.getElementById('payment-form');
                const submitBtn = document.getElementById('submit-payment');
                const messageEl = document.getElementById('payment-message');
                const paymentElementContainer = document.getElementById('payment-element');

                if (!paymentForm || !submitBtn || !messageEl || !paymentElementContainer || typeof Stripe === 'undefined') {
                    return;
                }

                const clientSecret = paymentForm.dataset.clientSecret;
                const stripeAccountId = paymentForm.dataset.stripeAccount || '';

                if (!clientSecret) {
                    return;
                }

                if (!stripeState.stripe || stripeState.currentStripeAccountId !== stripeAccountId) {
                    stripeState.stripe = stripeAccountId ?
                        Stripe(paymentForm.dataset.stripeKey, {
                            stripeAccount: stripeAccountId
                        }) :
                        Stripe(paymentForm.dataset.stripeKey);
                    stripeState.currentStripeAccountId = stripeAccountId;
                    stripeState.currentClientSecret = null;
                }

                if (stripeState.currentClientSecret === clientSecret && paymentElementContainer.childElementCount > 0) {
                    submitBtn.textContent = paymentForm.dataset.payLabel;
                    if (stripeState.elements) stripeState.elements.fetchUpdates();
                    return;
                }

                paymentElementContainer.innerHTML = '';
                const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                stripeState.elements = stripeState.stripe.elements({
                    clientSecret,
                    appearance: {
                        theme: isDark ? 'night' : 'stripe'
                    }
                });

                const paymentElement = stripeState.elements.create('payment', {
                    paymentMethodOrder: ['apple_pay', 'google_pay', 'card', 'cashapp', 'us_bank_account'],
                    layout: {
                        type: 'accordion',
                        visibleAccordionItemsCount: 5,
                    },
                });
                paymentElement.mount('#payment-element');

                stripeState.currentClientSecret = clientSecret;
                submitBtn.textContent = paymentForm.dataset.payLabel;

                submitBtn.onclick = async () => {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Processing...';
                    messageEl.classList.add('hidden');

                    const wireEl = paymentForm.closest('[wire\\:id]');
                    if (wireEl) {
                        try {
                            await Livewire.find(wireEl.getAttribute('wire:id')).call('syncNoteToPaymentIntent');
                        } catch (e) {}
                    }

                    const {
                        error
                    } = await stripeState.stripe.confirmPayment({
                        elements: stripeState.elements,
                        confirmParams: {
                            return_url: paymentForm.dataset.returnUrl,
                        },
                    });

                    if (error) {
                        messageEl.textContent = error.message;
                        messageEl.classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitBtn.textContent = paymentForm.dataset.payLabel;
                    }
                };
            };

            mountStripePaymentElement();

            if (!stripeState.isListening) {
                window.addEventListener('stripe-payment-intent-updated', mountStripePaymentElement);
                stripeState.isListening = true;
            }
        </script>
    @endpush
@endonce
