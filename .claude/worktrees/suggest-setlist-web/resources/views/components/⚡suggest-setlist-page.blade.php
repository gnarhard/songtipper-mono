<?php

declare(strict_types=1);

use App\Enums\SongTheme;
use App\Mail\SetlistSuggestedMail;
use App\Models\Project;
use App\Models\ProjectSong;
use App\Services\SuggestedSetlistRateLimiter;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Project $project;

    public bool $submitted = false;

    #[Url]
    public string $search = '';

    /** @var list<int> */
    public array $selectedProjectSongIds = [];

    #[Validate('required|string|max:255')]
    public string $submitterName = '';

    #[Validate('required|email|max:255')]
    public string $submitterEmail = '';

    #[Validate('nullable|string|max:255')]
    public string $eventName = '';

    #[Validate('nullable|string|max:3000')]
    public string $note = '';

    public string $website = '';

    #[Locked]
    public int $loadedAt = 0;

    public function mount(string $projectSlug): void
    {
        $this->project = Project::query()
            ->where('slug', $projectSlug)
            ->with(['owner', 'publicRepertoireSet'])
            ->firstOrFail();

        $this->loadedAt = now()->timestamp;
    }

    public function updated(string $property): void
    {
        if ($property === 'search') {
            $this->resetPage();
        }
    }

    public function togglePick(int $projectSongId): void
    {
        $index = array_search($projectSongId, $this->selectedProjectSongIds, true);

        if ($index !== false) {
            array_splice($this->selectedProjectSongIds, $index, 1);
            $this->selectedProjectSongIds = array_values($this->selectedProjectSongIds);
        } else {
            if (count($this->selectedProjectSongIds) >= $this->maxSongs) {
                return;
            }
            $this->selectedProjectSongIds[] = $projectSongId;
        }
    }

    public function removePick(int $projectSongId): void
    {
        $index = array_search($projectSongId, $this->selectedProjectSongIds, true);

        if ($index !== false) {
            array_splice($this->selectedProjectSongIds, $index, 1);
            $this->selectedProjectSongIds = array_values($this->selectedProjectSongIds);
        }
    }

    public function clearPicks(): void
    {
        $this->selectedProjectSongIds = [];
    }

    #[Computed]
    public function repertoire()
    {
        $query = $this->publicRepertoireQuery();

        $query->select([
            'project_songs.id',
            'project_songs.title',
            'project_songs.artist',
            'project_songs.instrumental',
        ]);

        if ($this->search !== '') {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchTerm): void {
                $q->where('project_songs.title', 'like', $searchTerm)
                    ->orWhere('project_songs.artist', 'like', $searchTerm);
            });
        }

        $query->orderBy('project_songs.title');

        return $query->paginate(50);
    }

    #[Computed]
    public function pickedSongs()
    {
        if (empty($this->selectedProjectSongIds)) {
            return collect();
        }

        $songs = ProjectSong::query()
            ->where('project_id', $this->project->id)
            ->whereIn('id', $this->selectedProjectSongIds)
            ->get();

        $idOrder = array_flip($this->selectedProjectSongIds);

        return $songs->sortBy(fn (ProjectSong $ps): int => $idOrder[$ps->id] ?? PHP_INT_MAX)->values();
    }

    #[Computed]
    public function minSongs(): int
    {
        return $this->project->min_suggested_setlist_songs ?? 5;
    }

    #[Computed]
    public function maxSongs(): int
    {
        return $this->project->max_suggested_setlist_songs ?? 25;
    }

    #[Computed]
    public function canSubmit(): bool
    {
        $count = count($this->selectedProjectSongIds);

        return $count >= $this->minSongs && $count <= $this->maxSongs;
    }

    public function submit(): void
    {
        if ($this->website !== '' || (now()->timestamp - $this->loadedAt) < 3) {
            $this->submitted = true;
            $this->reset(['submitterName', 'submitterEmail', 'eventName', 'note', 'website', 'selectedProjectSongIds']);

            return;
        }

        $this->validate();

        $count = count($this->selectedProjectSongIds);

        if ($count < $this->minSongs) {
            $this->addError('selectedProjectSongIds', "Please select at least {$this->minSongs} songs.");

            return;
        }

        if ($count > $this->maxSongs) {
            $this->addError('selectedProjectSongIds', "Please select no more than {$this->maxSongs} songs.");

            return;
        }

        $validSongIds = $this->publicRepertoireQuery()
            ->whereIn('project_songs.id', $this->selectedProjectSongIds)
            ->pluck('project_songs.id')
            ->all();

        $orderedValidIds = array_values(array_filter(
            $this->selectedProjectSongIds,
            fn (int $id): bool => in_array($id, $validSongIds, true),
        ));

        if (count($orderedValidIds) < $this->minSongs) {
            $this->addError('selectedProjectSongIds', 'Some selected songs are no longer available. Please update your selection.');
            $this->selectedProjectSongIds = $orderedValidIds;

            return;
        }

        $rateLimiter = app(SuggestedSetlistRateLimiter::class);

        if (! $rateLimiter->canSubmit($this->project, request()->ip())) {
            $this->addError('selectedProjectSongIds', 'You have reached the maximum number of submissions for today. Please try again tomorrow.');

            return;
        }

        $rateLimiter->hit($this->project, request()->ip());

        if ($this->project->notify_on_request && $this->project->owner?->email) {
            Mail::to($this->project->owner->email)->queue(
                new SetlistSuggestedMail(
                    project: $this->project,
                    submitterName: $this->submitterName,
                    submitterEmail: $this->submitterEmail,
                    eventName: $this->eventName !== '' ? $this->eventName : null,
                    note: $this->note !== '' ? $this->note : null,
                    projectSongIds: $orderedValidIds,
                )
            );
        }

        $this->submitted = true;
        $this->reset(['submitterName', 'submitterEmail', 'eventName', 'note', 'website', 'selectedProjectSongIds', 'search']);
    }

    private function publicRepertoireQuery()
    {
        if ($this->project->public_repertoire_set_id !== null) {
            $setId = $this->project->public_repertoire_set_id;
            $query = $this->project->projectSongs()
                ->join('setlist_songs', function (JoinClause $join) use ($setId): void {
                    $join->on('setlist_songs.project_song_id', '=', 'project_songs.id')
                        ->where('setlist_songs.set_id', $setId);
                })
                ->whereNotNull('setlist_songs.project_song_id')
                ->join('songs', 'project_songs.song_id', '=', 'songs.id')
                ->distinct();
        } else {
            $query = $this->project->projectSongs()
                ->where('project_songs.is_public', true)
                ->join('songs', 'project_songs.song_id', '=', 'songs.id');
        }

        if (now()->month === 12) {
            $query->whereRaw('coalesce(project_songs.theme, songs.theme) = ?', [SongTheme::Christmas->value]);
        }

        return $query;
    }
};
?>

<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
    @if ($submitted)
        <div class="rounded-lg bg-success-50 p-8 text-center dark:bg-success-900/20">
            <svg class="mx-auto h-12 w-12 text-success-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-semibold text-success-700 dark:text-success-200">Setlist Sent!</h3>
            <p class="mt-2 text-success-700 dark:text-success-300">
                Your suggested setlist has been sent to {{ $project->name }}. Thanks for putting it together!
            </p>
            <button
                wire:click="$set('submitted', false)"
                class="mt-4 text-sm text-success-600 hover:underline dark:text-success-300"
            >
                Submit another setlist
            </button>
        </div>
    @else
        {{-- Header --}}
        <div class="mb-8 text-center">
            @if ($project->performer_profile_image_url)
                <img
                    src="{{ $project->performer_profile_image_url }}"
                    alt="{{ $project->name }}"
                    class="mx-auto mb-4 h-20 w-20 rounded-full object-cover ring-2 ring-brand-500/30"
                >
            @endif
            <h1 class="text-2xl font-bold text-ink dark:text-ink-inverse">
                Suggest a Setlist
            </h1>
            <p class="mt-2 text-ink-muted dark:text-ink-soft">
                Pick {{ $this->minSongs }}–{{ $this->maxSongs }} songs for <strong>{{ $project->name }}</strong>
            </p>
        </div>

        {{-- Picked strip --}}
        <div class="mb-6 rounded-lg border border-ink-border bg-surface p-4 dark:border-ink-border-dark dark:bg-surface-elevated">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-ink dark:text-ink-inverse">
                    Your Picks
                    <span class="ml-2 text-xs font-normal {{ count($selectedProjectSongIds) >= $this->minSongs && count($selectedProjectSongIds) <= $this->maxSongs ? 'text-success-600 dark:text-success-400' : 'text-ink-muted dark:text-ink-soft' }}">
                        {{ count($selectedProjectSongIds) }} / {{ $this->minSongs }}–{{ $this->maxSongs }}
                    </span>
                </h2>
                @if (count($selectedProjectSongIds) > 0)
                    <button
                        type="button"
                        wire:click="clearPicks"
                        class="text-xs text-red-600 hover:underline dark:text-red-400"
                    >
                        Clear all
                    </button>
                @endif
            </div>

            @if (count($selectedProjectSongIds) === 0)
                <p class="text-sm text-ink-muted dark:text-ink-soft">
                    Pick at least {{ $this->minSongs }} songs from the list below to get started.
                </p>
            @else
                <ol class="space-y-1">
                    @foreach ($this->pickedSongs as $index => $song)
                        <li class="flex items-center justify-between rounded px-2 py-1.5 text-sm hover:bg-surface-muted dark:hover:bg-surface">
                            <span>
                                <span class="mr-2 text-xs font-medium text-ink-muted dark:text-ink-soft">{{ $index + 1 }}.</span>
                                <span class="font-medium text-ink dark:text-ink-inverse">{{ $song->title }}{{ $song->instrumental ? ' (instrumental)' : '' }}</span>
                                <span class="text-ink-muted dark:text-ink-soft"> — {{ $song->artist }}</span>
                            </span>
                            <button
                                type="button"
                                wire:click="removePick({{ $song->id }})"
                                class="ml-2 text-xs text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                title="Remove"
                            >&times;</button>
                        </li>
                    @endforeach
                </ol>
            @endif

            @error('selectedProjectSongIds')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Search --}}
        <div class="mb-4">
            <x-text-input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search songs by title or artist..."
                class="w-full"
            />
        </div>

        {{-- Song list --}}
        <div class="mb-8 overflow-hidden rounded-lg border border-ink-border dark:border-ink-border-dark">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-border bg-surface-muted text-left text-xs font-medium uppercase tracking-wider text-ink-muted dark:border-ink-border-dark dark:bg-surface-elevated dark:text-ink-soft">
                        <th class="px-4 py-3 w-12"></th>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Artist</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-border dark:divide-ink-border-dark">
                    @forelse ($this->repertoire as $song)
                        @php
                            $isPicked = in_array($song->id, $selectedProjectSongIds, true);
                            $isMaxed = count($selectedProjectSongIds) >= $this->maxSongs && ! $isPicked;
                        @endphp
                        <tr
                            wire:key="song-{{ $song->id }}"
                            class="{{ $isPicked ? 'bg-brand-50 dark:bg-brand-900/20' : '' }} {{ $isMaxed ? 'opacity-50' : 'cursor-pointer hover:bg-surface-muted dark:hover:bg-surface-elevated' }}"
                            @if (! $isMaxed) wire:click="togglePick({{ $song->id }})" @endif
                        >
                            <td class="px-4 py-3 text-center">
                                @if ($isPicked)
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">
                                        {{ array_search($song->id, $selectedProjectSongIds, true) + 1 }}
                                    </span>
                                @else
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-ink-border text-xs text-ink-muted dark:border-ink-border-dark dark:text-ink-soft">
                                        +
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-ink dark:text-ink-inverse">
                                {{ $song->title }}{{ $song->instrumental ? ' (instrumental)' : '' }}
                            </td>
                            <td class="px-4 py-3 text-ink-muted dark:text-ink-soft">{{ $song->artist }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-ink-muted dark:text-ink-soft">
                                {{ $search !== '' ? 'No songs match your search.' : 'No songs available.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->repertoire->hasPages())
            <div class="mb-8">
                {{ $this->repertoire->links() }}
            </div>
        @endif

        {{-- Submitter form --}}
        <div class="rounded-lg border border-ink-border bg-surface p-6 dark:border-ink-border-dark dark:bg-surface-elevated">
            <h2 class="mb-4 text-lg font-semibold text-ink dark:text-ink-inverse">Your Info</h2>

            {{-- Honeypot --}}
            <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" wire:model="website" autocomplete="off" tabindex="-1" />
            </div>

            <div class="space-y-4">
                <div>
                    <label for="submitterName" class="block text-sm font-medium text-ink-muted dark:text-ink-soft">
                        Your Name <span class="text-red-500">*</span>
                    </label>
                    <x-text-input
                        type="text"
                        id="submitterName"
                        wire:model="submitterName"
                        class="mt-1 w-full"
                        placeholder="Your name"
                    />
                    @error('submitterName')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="submitterEmail" class="block text-sm font-medium text-ink-muted dark:text-ink-soft">
                        Your Email <span class="text-red-500">*</span>
                    </label>
                    <x-text-input
                        type="email"
                        id="submitterEmail"
                        wire:model="submitterEmail"
                        class="mt-1 w-full"
                        placeholder="you@example.com"
                    />
                    @error('submitterEmail')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="eventName" class="block text-sm font-medium text-ink-muted dark:text-ink-soft">
                        Event / Occasion
                    </label>
                    <x-text-input
                        type="text"
                        id="eventName"
                        wire:model="eventName"
                        class="mt-1 w-full"
                        placeholder="e.g. Sarah's birthday, Friday at the Blue Door"
                    />
                    @error('eventName')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="note" class="block text-sm font-medium text-ink-muted dark:text-ink-soft">
                        Note to Performer
                    </label>
                    <x-textarea-input
                        id="note"
                        wire:model="note"
                        rows="3"
                        class="mt-1 w-full"
                        placeholder="Any special requests or notes..."
                    ></x-textarea-input>
                    @error('note')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <x-primary-button
                    type="button"
                    wire:click="submit"
                    class="w-full disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="! $this->canSubmit"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="submit">
                        Send Setlist ({{ count($selectedProjectSongIds) }} {{ Str::plural('song', count($selectedProjectSongIds)) }})
                    </span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center">
                        <svg class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Sending...
                    </span>
                </x-primary-button>
            </div>
        </div>

        {{-- Attribution --}}
        <div class="mt-6 text-center">
            <a
                href="{{ route('home') }}"
                target="_blank"
                rel="noopener noreferrer"
                class="text-xs text-ink-muted hover:text-ink-subtle dark:text-ink-soft dark:hover:text-ink-muted"
            >
                Powered by {{ config('app.name') }}
            </a>
        </div>
    @endif
</div>
