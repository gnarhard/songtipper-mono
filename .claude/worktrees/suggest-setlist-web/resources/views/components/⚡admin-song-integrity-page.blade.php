<?php

use App\Models\Song;
use App\Models\SongIntegrityIssue;
use App\Services\SongDataIntegrityService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'severity')]
    public string $filterSeverity = '';

    #[Url(as: 'type')]
    public string $filterType = '';

    #[Url(as: 'status')]
    public string $filterStatus = 'open';

    public ?string $statusMessage = null;

    public ?string $statusType = 'success';

    public array $selected = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    #[Computed]
    public function issues()
    {
        $query = SongIntegrityIssue::query()
            ->with('song')
            ->whereHas('song')
            ->orderByRaw("CASE severity WHEN 'error' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderBy('id');

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterSeverity !== '') {
            $query->where('severity', $this->filterSeverity);
        }

        if ($this->filterType !== '') {
            $query->where('issue_type', $this->filterType);
        }

        return $query->paginate(25);
    }

    #[Computed]
    public function openCount(): int
    {
        return SongIntegrityIssue::where('status', 'open')->whereHas('song')->count();
    }

    #[Computed]
    public function resolvedCount(): int
    {
        return SongIntegrityIssue::where('status', 'resolved')->whereHas('song')->count();
    }

    #[Computed]
    public function dismissedCount(): int
    {
        return SongIntegrityIssue::where('status', 'dismissed')->whereHas('song')->count();
    }

    #[Computed]
    public function issueTypes(): array
    {
        return SongIntegrityIssue::query()
            ->distinct()
            ->pluck('issue_type')
            ->sort()
            ->values()
            ->all();
    }

    public function updatedFilterSeverity(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function toggleSelectAll(): void
    {
        $pageIds = $this->issues->pluck('id')->all();

        if (count(array_intersect($this->selected, $pageIds)) === count($pageIds)) {
            $this->selected = array_values(array_diff($this->selected, $pageIds));
        } else {
            $this->selected = array_values(array_unique(array_merge($this->selected, $pageIds)));
        }
    }

    public function applyFix(int $issueId): void
    {
        $issue = SongIntegrityIssue::findOrFail($issueId);
        $service = app(SongDataIntegrityService::class);

        if ($service->applySuggestedFix($issue)) {
            $this->statusMessage = "Applied fix: {$issue->field} changed to \"{$issue->suggested_value}\" for \"{$issue->song->title}\" by {$issue->song->artist}.";
            $this->statusType = 'success';
        } else {
            $this->statusMessage = 'Could not apply fix — missing field or suggested value.';
            $this->statusType = 'error';
        }

        $this->selected = array_values(array_diff($this->selected, [$issueId]));
        unset($this->issues, $this->openCount, $this->resolvedCount);
    }

    public function dismiss(int $issueId): void
    {
        $issue = SongIntegrityIssue::findOrFail($issueId);
        $issue->dismiss();

        $this->statusMessage = "Dismissed {$issue->issue_type} issue for \"{$issue->song->title}\".";
        $this->statusType = 'success';

        $this->selected = array_values(array_diff($this->selected, [$issueId]));
        unset($this->issues, $this->openCount, $this->dismissedCount);
    }

    public function applySelected(): void
    {
        if ($this->selected === []) {
            return;
        }

        $service = app(SongDataIntegrityService::class);
        $issues = SongIntegrityIssue::query()
            ->whereIn('id', $this->selected)
            ->where('status', 'open')
            ->whereNotNull('field')
            ->whereNotNull('suggested_value')
            ->with('song')
            ->get();

        $applied = 0;
        foreach ($issues as $issue) {
            if ($service->applySuggestedFix($issue)) {
                $applied++;
            }
        }

        $this->statusMessage = "Applied {$applied} fix(es) from " . count($this->selected) . ' selected issue(s).';
        $this->statusType = 'success';
        $this->selected = [];

        unset($this->issues, $this->openCount, $this->resolvedCount);
    }

    public function dismissSelected(): void
    {
        if ($this->selected === []) {
            return;
        }

        $count = SongIntegrityIssue::query()
            ->whereIn('id', $this->selected)
            ->where('status', 'open')
            ->update([
                'status' => 'dismissed',
                'resolved_at' => now(),
            ]);

        $this->statusMessage = "Dismissed {$count} selected issue(s).";
        $this->statusType = 'success';
        $this->selected = [];

        unset($this->issues, $this->openCount, $this->dismissedCount);
    }

    public function applyAll(): void
    {
        $service = app(SongDataIntegrityService::class);
        $issues = SongIntegrityIssue::query()
            ->where('status', 'open')
            ->whereNotNull('field')
            ->whereNotNull('suggested_value')
            ->with('song')
            ->get();

        $applied = 0;
        foreach ($issues as $issue) {
            if ($service->applySuggestedFix($issue)) {
                $applied++;
            }
        }

        $this->statusMessage = "Applied {$applied} fix(es).";
        $this->statusType = 'success';
        $this->selected = [];

        unset($this->issues, $this->openCount, $this->resolvedCount);
    }

    public function dismissAll(): void
    {
        $count = SongIntegrityIssue::query()
            ->where('status', 'open')
            ->update([
                'status' => 'dismissed',
                'resolved_at' => now(),
            ]);

        $this->statusMessage = "Dismissed {$count} issue(s).";
        $this->statusType = 'success';
        $this->selected = [];

        unset($this->issues, $this->openCount, $this->dismissedCount);
    }

    public function severityColor(string $severity): string
    {
        return match ($severity) {
            'error' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
            'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            default => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        };
    }
};
?>

<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        {{-- Stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <button wire:click="$set('filterStatus', 'open')" class="rounded-2xl border border-ink-border/80 bg-surface/95 p-5 text-left shadow-sm transition hover:border-brand/50 dark:border-ink-border-dark/80 dark:bg-surface-inverse/90 {{ $filterStatus === 'open' ? 'ring-2 ring-brand/40' : '' }}">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Open Issues</p>
                <p class="mt-2 text-3xl font-bold {{ $this->openCount > 0 ? 'text-ink-soft' : 'text-success-500' }}">{{ number_format($this->openCount) }}</p>
            </button>
            <button wire:click="$set('filterStatus', 'resolved')" class="rounded-2xl border border-ink-border/80 bg-surface/95 p-5 text-left shadow-sm transition hover:border-brand/50 dark:border-ink-border-dark/80 dark:bg-surface-inverse/90 {{ $filterStatus === 'resolved' ? 'ring-2 ring-brand/40' : '' }}">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Resolved</p>
                <p class="mt-2 text-3xl font-bold text-success-500">{{ number_format($this->resolvedCount) }}</p>
            </button>
            <button wire:click="$set('filterStatus', 'dismissed')" class="rounded-2xl border border-ink-border/80 bg-surface/95 p-5 text-left shadow-sm transition hover:border-brand/50 dark:border-ink-border-dark/80 dark:bg-surface-inverse/90 {{ $filterStatus === 'dismissed' ? 'ring-2 ring-brand/40' : '' }}">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Dismissed</p>
                <p class="mt-2 text-3xl font-bold text-ink-muted dark:text-ink-soft">{{ number_format($this->dismissedCount) }}</p>
            </button>
        </div>

        {{-- Status Message --}}
        @if ($statusMessage)
            <div class="rounded-xl border px-4 py-3 text-sm
                {{ $statusType === 'success'
                    ? 'border-success-100 bg-success-50 text-success-700 dark:border-success-700/60 dark:bg-success-900/20 dark:text-success-200'
                    : 'border-red-100 bg-red-50 text-red-700 dark:border-red-700/60 dark:bg-red-900/20 dark:text-red-200' }}">
                {{ $statusMessage }}
            </div>
        @endif

        {{-- Filters & Bulk Actions --}}
        <div class="rounded-2xl border border-ink-border/80 bg-surface/95 shadow-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
            <div class="border-b border-ink-border px-4 py-5 sm:px-6 dark:border-ink-border-dark">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Integrity Issues</h3>
                        <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
                            Review issues flagged by AI and rule-based checks. Apply suggested fixes or dismiss false positives.
                        </p>
                    </div>
                    @if ($filterStatus === 'open' && $this->openCount > 0)
                        <div class="flex flex-wrap items-center gap-2">
                            @if (count($selected) > 0)
                                <span class="text-xs font-medium text-ink-muted dark:text-ink-soft">{{ count($selected) }} selected</span>
                                <x-secondary-button wire:click="dismissSelected" wire:confirm="Dismiss {{ count($selected) }} selected issue(s)?" type="button" class="px-3 py-1.5 text-xs">
                                    Dismiss Selected
                                </x-secondary-button>
                                <x-primary-button wire:click="applySelected" wire:confirm="Apply fixes for {{ count($selected) }} selected issue(s)?" type="button" class="px-3 py-1.5 text-xs">
                                    Apply Selected
                                </x-primary-button>
                            @else
                                <x-secondary-button wire:click="dismissAll" wire:confirm="Dismiss all open issues?" type="button" class="px-3 py-1.5 text-xs">
                                    Dismiss All
                                </x-secondary-button>
                                <x-primary-button wire:click="applyAll" wire:confirm="Apply all suggested fixes? This will modify song data." type="button" class="px-3 py-1.5 text-xs">
                                    Apply All Fixes
                                </x-primary-button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Filters --}}
                <div class="mt-4 flex flex-wrap gap-3">
                    <select wire:model.live="filterSeverity" class="rounded-lg border-ink-border bg-surface px-3 py-1.5 text-sm text-ink dark:border-ink-border-dark dark:bg-surface-elevated dark:text-ink-inverse">
                        <option value="">All Severities</option>
                        <option value="error">Error</option>
                        <option value="warning">Warning</option>
                        <option value="info">Info</option>
                    </select>
                    <select wire:model.live="filterType" class="rounded-lg border-ink-border bg-surface px-3 py-1.5 text-sm text-ink dark:border-ink-border-dark dark:bg-surface-elevated dark:text-ink-inverse">
                        <option value="">All Types</option>
                        @foreach ($this->issueTypes as $type)
                            <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Mobile card list --}}
            <div class="divide-y divide-ink-border md:hidden dark:divide-ink-border-dark">
                @forelse ($this->issues as $issue)
                    <div wire:key="issue-{{ $issue->id }}" class="px-4 py-4">
                        <div class="flex items-start gap-3">
                            @if ($issue->status === 'open')
                                <input
                                    type="checkbox"
                                    value="{{ $issue->id }}"
                                    wire:model.live="selected"
                                    class="mt-1 rounded border-ink-border text-brand focus:ring-brand dark:border-ink-border-dark"
                                >
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-ink dark:text-ink-inverse">{{ $issue->song?->title ?? 'Deleted Song' }}</p>
                                        <p class="text-sm text-ink-muted dark:text-ink-soft">{{ $issue->song?->artist ?? '—' }}</p>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $this->severityColor($issue->severity) }}">
                                        {{ $issue->severity }}
                                    </span>
                                </div>
                                <div class="mt-2 space-y-1 text-sm">
                                    <p class="text-ink-muted dark:text-ink-soft">
                                        <span class="font-medium text-ink dark:text-ink-inverse">{{ str_replace('_', ' ', $issue->issue_type) }}</span>
                                        @if ($issue->field)
                                            on <span class="font-medium">{{ $issue->field }}</span>
                                        @endif
                                    </p>
                                    @if ($issue->explanation)
                                        <p class="text-ink-muted dark:text-ink-soft">{{ $issue->explanation }}</p>
                                    @endif
                                    @if ($issue->suggested_value)
                                        <p class="text-ink dark:text-ink-inverse">
                                            <span class="text-ink-muted dark:text-ink-soft">Current:</span> {{ $issue->current_value ?? '—' }}
                                            <span class="mx-1 text-ink-muted dark:text-ink-soft">&rarr;</span>
                                            <span class="font-medium text-success-600 dark:text-success-400">{{ $issue->suggested_value }}</span>
                                        </p>
                                    @endif
                                </div>
                                @if ($issue->status === 'open')
                                    <div class="mt-3 flex gap-2">
                                        @if ($issue->suggested_value && $issue->field)
                                            <x-primary-button wire:click="applyFix({{ $issue->id }})" type="button" class="px-3 py-1.5 text-xs">
                                                Apply Fix
                                            </x-primary-button>
                                        @endif
                                        <x-secondary-button wire:click="dismiss({{ $issue->id }})" type="button" class="px-3 py-1.5 text-xs">
                                            Dismiss
                                        </x-secondary-button>
                                    </div>
                                @else
                                    <p class="mt-2 text-xs text-ink-muted dark:text-ink-soft">
                                        {{ ucfirst($issue->status) }} {{ $issue->resolved_at?->diffForHumans() }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-12 text-center text-ink-muted dark:text-ink-soft">
                        No issues found matching the current filters.
                    </div>
                @endforelse
            </div>

            {{-- Desktop table --}}
            <div class="hidden md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-border bg-surface-muted text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:border-ink-border-dark dark:bg-surface-elevated dark:text-ink-soft">
                        <tr>
                            @if ($filterStatus === 'open')
                                <th class="w-10 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleSelectAll"
                                        @checked(count($selected) > 0 && count(array_intersect($selected, $this->issues->pluck('id')->all())) === $this->issues->count())
                                        class="rounded border-ink-border text-brand focus:ring-brand dark:border-ink-border-dark"
                                    >
                                </th>
                            @endif
                            <th class="px-4 py-3">Song</th>
                            <th class="px-4 py-3">Issue</th>
                            <th class="px-4 py-3">Field</th>
                            <th class="px-4 py-3">Severity</th>
                            <th class="px-4 py-3">Suggested Fix</th>
                            <th class="px-4 py-3">Explanation</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-border dark:divide-ink-border-dark">
                        @forelse ($this->issues as $issue)
                            <tr wire:key="issue-{{ $issue->id }}" class="transition hover:bg-surface-muted dark:hover:bg-surface-elevated {{ in_array($issue->id, $selected) ? 'bg-brand/5 dark:bg-brand/10' : '' }}">
                                @if ($filterStatus === 'open')
                                    <td class="px-4 py-3">
                                        @if ($issue->status === 'open')
                                            <input
                                                type="checkbox"
                                                value="{{ $issue->id }}"
                                                wire:model.live="selected"
                                                class="rounded border-ink-border text-brand focus:ring-brand dark:border-ink-border-dark"
                                            >
                                        @endif
                                    </td>
                                @endif
                                <td class="px-4 py-3">
                                    <p class="font-medium text-ink dark:text-ink-inverse">{{ $issue->song?->title ?? 'Deleted' }}</p>
                                    <p class="text-xs text-ink-muted dark:text-ink-soft">{{ $issue->song?->artist ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-ink-muted dark:text-ink-soft">
                                    {{ str_replace('_', ' ', $issue->issue_type) }}
                                </td>
                                <td class="px-4 py-3 text-ink-muted dark:text-ink-soft">
                                    {{ $issue->field ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $this->severityColor($issue->severity) }}">
                                        {{ $issue->severity }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($issue->suggested_value)
                                        <div class="max-w-xs">
                                            <p class="text-xs text-ink-muted line-through dark:text-ink-soft">{{ $issue->current_value ?? $issue->song?->{$issue->field} ?? '—' }}</p>
                                            <p class="font-medium text-success-600 dark:text-success-400">{{ $issue->suggested_value }}</p>
                                        </div>
                                    @else
                                        <span class="text-ink-muted/50 dark:text-ink-soft/50">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-ink-muted dark:text-ink-soft">
                                        {{ $issue->explanation ?? '—' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($issue->status === 'open')
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($issue->suggested_value && $issue->field)
                                                <button
                                                    wire:click="applyFix({{ $issue->id }})"
                                                    class="rounded-lg p-1.5 text-ink-muted transition hover:bg-success-50 hover:text-success-600 dark:text-ink-soft dark:hover:bg-success-900/30 dark:hover:text-success-300"
                                                    title="Apply suggested fix"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                </button>
                                            @endif
                                            <button
                                                wire:click="dismiss({{ $issue->id }})"
                                                class="rounded-lg p-1.5 text-ink-muted transition hover:bg-red-50 hover:text-red-600 dark:text-ink-soft dark:hover:bg-red-900/30 dark:hover:text-red-300"
                                                title="Dismiss issue"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    @else
                                        <p class="text-right text-xs text-ink-muted dark:text-ink-soft">
                                            {{ ucfirst($issue->status) }}
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $filterStatus === 'open' ? 8 : 7 }}" class="px-4 py-12 text-center text-ink-muted dark:text-ink-soft">
                                    No issues found matching the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($this->issues->hasPages())
                <div class="border-t border-ink-border px-4 py-4 sm:px-6 dark:border-ink-border-dark">
                    {{ $this->issues->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
