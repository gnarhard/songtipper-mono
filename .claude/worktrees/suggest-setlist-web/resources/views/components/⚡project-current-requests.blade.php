<?php

use App\Models\AudienceProfile;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\RewardThreshold;
use App\Services\AudienceIdentityService;
use App\Services\RewardThresholdService;
use App\Support\RewardIconOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorInstance;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public const PREVIOUSLY_PLAYED_PAGE_NAME = 'playedPage';

    public const PREVIOUSLY_PLAYED_PER_PAGE = 3;

    public Project $project;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    #[Computed]
    public function audienceProfile(): ?AudienceProfile
    {
        return app(AudienceIdentityService::class)
            ->findProfile(
                $this->project,
                request()->cookie(AudienceIdentityService::VISITOR_COOKIE),
            );
    }

    /**
     * @return Collection<int, array{request: SongRequest, queue_position: int}>
     */
    #[Computed]
    public function currentRequests(): Collection
    {
        $audienceProfileId = $this->audienceProfile?->id;

        $requestIdFromSession = (int) session('request_success.request_id', 0);

        if ($audienceProfileId === null && $requestIdFromSession <= 0) {
            return collect();
        }

        return SongRequest::query()
            ->with('song')
            ->where('project_id', $this->project->id)
            ->active()
            ->queueOrdered()
            ->get()
            ->values()
            ->map(function (SongRequest $songRequest, int $index): array {
                return [
                    'request' => $songRequest,
                    'queue_position' => $index + 1,
                ];
            })
            ->filter(function (array $entry) use ($audienceProfileId, $requestIdFromSession): bool {
                /** @var SongRequest $songRequest */
                $songRequest = $entry['request'];

                return ($audienceProfileId !== null && $songRequest->audience_profile_id === $audienceProfileId)
                    || ($requestIdFromSession > 0 && $songRequest->id === $requestIdFromSession);
            })
            ->unique(fn (array $entry): int => $entry['request']->id)
            ->values();
    }

    /**
     * @return LengthAwarePaginator<int, SongRequest>
     */
    #[Computed]
    public function previousRequests(): LengthAwarePaginator
    {
        $profile = $this->audienceProfile;

        if ($profile === null) {
            return new LengthAwarePaginatorInstance(
                items: [],
                total: 0,
                perPage: self::PREVIOUSLY_PLAYED_PER_PAGE,
                currentPage: 1,
                options: [
                    'pageName' => self::PREVIOUSLY_PLAYED_PAGE_NAME,
                ],
            );
        }

        return SongRequest::query()
            ->with('song')
            ->where('project_id', $this->project->id)
            ->where('audience_profile_id', $profile->id)
            ->played()
            ->orderByDesc('played_at')
            ->paginate(
                perPage: self::PREVIOUSLY_PLAYED_PER_PAGE,
                pageName: self::PREVIOUSLY_PLAYED_PAGE_NAME,
            );
    }

    #[Computed]
    public function totalTippedCents(): int
    {
        return (int) ($this->audienceProfile?->cumulative_tip_cents ?? 0);
    }

    /**
     * @return Collection<int, array{threshold: RewardThreshold, available_claims: int, cents_remaining: int, total_claims: int, is_exhausted: bool}>
     */
    #[Computed]
    public function thresholdProgress(): Collection
    {
        $profile = $this->audienceProfile;

        if ($profile === null) {
            return collect();
        }

        $this->project->loadMissing('rewardThresholds');

        if ($this->project->rewardThresholds->isEmpty()) {
            return collect();
        }

        return app(RewardThresholdService::class)->progressSummary($profile, $this->project);
    }
};
?>

<div>
    @if ($this->currentRequests->isNotEmpty() || $this->previousRequests->total() > 0)
        <x-ui.card wire:poll.60s.visible class="mb-4 p-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-accent dark:text-brand-300">
                        Your Requests
                    </p>
                </div>

                @if ($this->totalTippedCents > 0)
                    <p class="text-xs font-medium text-ink-muted dark:text-ink-soft">
                        Total tipped:
                        <span class="font-semibold text-ink dark:text-ink-inverse">
                            ${{ \App\Support\TipAmount::formatDisplay($this->totalTippedCents) }}
                        </span>
                    </p>
                @endif
            </div>

            @if ($this->currentRequests->isNotEmpty())
                <div class="mt-4">
                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                        In queue
                    </p>
                    <div class="overflow-hidden border border-ink-border/70 rounded-xl dark:border-ink-border-dark/70">
                        <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-4 border-b border-ink-border/70 bg-surface px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-ink-muted dark:border-ink-border-dark/70 dark:bg-surface-elevated dark:text-ink-soft">
                            <div>Song</div>
                            <div class="w-16 text-right">Tip</div>
                            <div class="w-24 text-right">Queue position</div>
                        </div>

                        <div class="space-y-0 divide-y divide-ink-border/70 dark:divide-ink-border-dark/70">
                            @foreach ($this->currentRequests as $entry)
                                <div wire:key="current-request-{{ $entry['request']->id }}" class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-4 bg-surface-muted px-4 py-3 hover:bg-surface transition dark:bg-canvas-dark/70 dark:hover:bg-canvas-dark">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-ink dark:text-ink-inverse">
                                            {{ $entry['request']->song->title }}
                                        </p>
                                        <p class="truncate text-xs text-ink-muted dark:text-ink-soft">
                                            {{ $entry['request']->song->artist }}
                                        </p>
                                    </div>

                                    <div class="w-16 text-right shrink-0">
                                        <p class="text-sm font-semibold text-ink dark:text-ink-inverse">
                                            ${{ \App\Support\TipAmount::formatDisplay($entry['request']->tip_amount_cents) }}
                                        </p>
                                    </div>

                                    <div class="w-24 text-right shrink-0">
                                        <p class="text-lg font-semibold text-accent dark:text-brand-300">
                                            #{{ $entry['queue_position'] }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if ($this->previousRequests->total() > 0)
                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                            Previously played
                        </p>
                        @if ($this->previousRequests->total() > $this->previousRequests->perPage())
                            <p class="text-[11px] font-medium text-ink-muted dark:text-ink-soft">
                                {{ $this->previousRequests->firstItem() }}–{{ $this->previousRequests->lastItem() }} of {{ $this->previousRequests->total() }}
                            </p>
                        @endif
                    </div>
                    <div class="overflow-hidden border border-ink-border/70 rounded-xl dark:border-ink-border-dark/70">
                        <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-4 border-b border-ink-border/70 bg-surface px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-ink-muted dark:border-ink-border-dark/70 dark:bg-surface-elevated dark:text-ink-soft">
                            <div>Song</div>
                            <div class="w-16 text-right">Tip</div>
                        </div>

                        <div class="space-y-0 divide-y divide-ink-border/70 dark:divide-ink-border-dark/70">
                            @foreach ($this->previousRequests as $pastRequest)
                                <div wire:key="previous-request-{{ $pastRequest->id }}" class="grid grid-cols-[minmax(0,1fr)_auto] gap-4 bg-surface-muted px-4 py-3 hover:bg-surface transition dark:bg-canvas-dark/70 dark:hover:bg-canvas-dark">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-ink dark:text-ink-inverse">
                                            {{ $pastRequest->song->title }}
                                        </p>
                                        <p class="truncate text-xs text-ink-muted dark:text-ink-soft">
                                            {{ $pastRequest->song->artist }}
                                        </p>
                                    </div>

                                    <div class="w-16 text-right shrink-0">
                                        <p class="text-sm font-semibold text-ink dark:text-ink-inverse">
                                            ${{ \App\Support\TipAmount::formatDisplay($pastRequest->tip_amount_cents) }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if ($this->previousRequests->hasPages())
                        <div class="mt-3 flex items-center justify-between gap-2 text-xs">
                            <button
                                type="button"
                                wire:click="previousPage('{{ self::PREVIOUSLY_PLAYED_PAGE_NAME }}')"
                                @disabled($this->previousRequests->onFirstPage())
                                class="inline-flex items-center gap-1 rounded-md border border-ink-border/70 bg-surface px-3 py-1.5 font-semibold text-ink hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-50 dark:border-ink-border-dark/70 dark:bg-canvas-dark/70 dark:text-ink-inverse dark:hover:bg-canvas-dark"
                            >
                                &larr; Newer
                            </button>

                            <p class="text-ink-muted dark:text-ink-soft">
                                Page {{ $this->previousRequests->currentPage() }} of {{ $this->previousRequests->lastPage() }}
                            </p>

                            <button
                                type="button"
                                wire:click="nextPage('{{ self::PREVIOUSLY_PLAYED_PAGE_NAME }}')"
                                @disabled(! $this->previousRequests->hasMorePages())
                                class="inline-flex items-center gap-1 rounded-md border border-ink-border/70 bg-surface px-3 py-1.5 font-semibold text-ink hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-50 dark:border-ink-border-dark/70 dark:bg-canvas-dark/70 dark:text-ink-inverse dark:hover:bg-canvas-dark"
                            >
                                Older &rarr;
                            </button>
                        </div>
                    @endif
                </div>
            @endif

            @if ($this->thresholdProgress->isNotEmpty())
                <div class="mt-4">
                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                        Reward progress
                    </p>
                    <div class="space-y-2">
                        @foreach ($this->thresholdProgress as $progress)
                            @php
                                $threshold = $progress['threshold'];
                                $label = $threshold->reward_type === \App\Models\RewardThreshold::TYPE_FREE_REQUEST
                                    ? 'Free request'
                                    : $threshold->reward_label;
                                $icon = RewardIconOptions::emoji($threshold->reward_icon);
                                $description = $threshold->reward_description;
                                $thresholdCents = (int) $threshold->threshold_cents;
                                $centsRemaining = (int) $progress['cents_remaining'];
                                $progressPct = $thresholdCents > 0
                                    ? max(0, min(100, (int) round((($thresholdCents - $centsRemaining) / $thresholdCents) * 100)))
                                    : 0;
                            @endphp
                            <div wire:key="threshold-{{ $threshold->id }}" class="rounded-xl border border-ink-border/70 bg-surface-muted px-4 py-3 dark:border-ink-border-dark/70 dark:bg-canvas-dark/70">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            @if ($icon !== null)
                                                <span class="text-base leading-none" aria-hidden="true">{{ $icon }}</span>
                                            @endif
                                            <p class="text-sm font-semibold text-ink dark:text-ink-inverse">
                                                {{ $label }}
                                            </p>
                                        </div>
                                        @if ($description !== null && $description !== '')
                                            <p class="mt-1 text-xs text-ink-muted dark:text-ink-soft">
                                                {{ $description }}
                                            </p>
                                        @endif
                                    </div>
                                    @if ($progress['available_claims'] > 0)
                                        <p class="shrink-0 text-xs font-semibold uppercase tracking-wide text-accent dark:text-brand-300">
                                            Earned!
                                        </p>
                                    @elseif ($progress['is_exhausted'])
                                        <p class="shrink-0 text-xs font-medium text-ink-muted dark:text-ink-soft">
                                            Claimed
                                        </p>
                                    @else
                                        <p class="shrink-0 text-xs font-medium text-ink-muted dark:text-ink-soft">
                                            ${{ \App\Support\TipAmount::formatDisplay($centsRemaining) }} to go
                                        </p>
                                    @endif
                                </div>
                                @if (! $progress['is_exhausted'])
                                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-accent/40 dark:bg-ink-border-dark">
                                        <div class="h-full bg-brand dark:bg-brand-400" style="width: {{ $progressPct }}%"></div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-ui.card>
    @endif
</div>
