<?php

use App\Enums\EnergyLevel;
use App\Enums\Era;
use App\Enums\Genre;
use App\Enums\MusicalKey;
use App\Enums\SongTheme;
use App\Models\Chart;
use App\Models\ProjectSong;
use App\Models\Request;
use App\Models\Song;
use App\Models\SongIntegrityIssue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $view = 'all';

    #[Url]
    public string $sortField = 'updated_at';

    #[Url]
    public string $sortDirection = 'desc';

    public ?int $editingSongId = null;

    public string $editTitle = '';

    public string $editArtist = '';

    public ?string $editEnergyLevel = null;

    public ?string $editEra = null;

    public ?string $editGenre = null;

    public ?string $editTheme = null;

    public ?string $editOriginalMusicalKey = null;

    public ?int $editDurationInSeconds = null;

    public ?string $statusMessage = null;

    public bool $showDeleteConfirm = false;

    public ?int $deletingSongId = null;

    /**
     * When true, deleting the song will also hard-delete all referencing
     * ProjectSong rows (and their charts via FK cascade). Only set by the
     * force-delete path in the merge modal.
     */
    public bool $deleteReferencingProjectSongs = false;

    public bool $showMergeModal = false;

    public ?int $mergingFromSongId = null;

    public string $mergeTargetSearch = '';

    public ?int $mergeTargetSongId = null;

    public ?string $errorMessage = null;

    #[Computed]
    public function songs()
    {
        if ($this->view === 'duplicates') {
            return $this->duplicateSongs();
        }

        $query = Song::query();

        if ($this->search !== '') {
            $query->search($this->search);
        }

        return $query
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(25);
    }

    private function duplicateSongs()
    {
        $duplicateKeys = Song::query()
            ->selectRaw('normalized_key')
            ->groupBy('normalized_key')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('normalized_key');

        $query = Song::query()
            ->whereIn('normalized_key', $duplicateKeys)
            ->withCount('projectSongs');

        if ($this->search !== '') {
            $query->search($this->search);
        }

        return $query
            ->orderBy('normalized_key')
            ->orderBy('id')
            ->paginate(50);
    }

    #[Computed]
    public function totalSongs(): int
    {
        return Song::count();
    }

    #[Computed]
    public function duplicateCount(): int
    {
        return Song::query()
            ->whereIn('normalized_key', function ($sub) {
                $sub->select('normalized_key')
                    ->from('songs')
                    ->whereNull('deleted_at')
                    ->groupBy('normalized_key')
                    ->havingRaw('COUNT(*) > 1');
            })
            ->count();
    }

    #[Computed]
    public function incompleteMetadataCount(): int
    {
        return Song::query()
            ->where(function ($q) {
                $q->whereNull('energy_level')
                    ->orWhereNull('era')
                    ->orWhereNull('genre')
                    ->orWhereNull('original_musical_key')
                    ->orWhereNull('duration_in_seconds');
            })
            ->count();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedView(): void
    {
        $this->resetPage();
        unset($this->songs);
    }

    public function startEditing(int $songId): void
    {
        $song = Song::findOrFail($songId);

        $this->editingSongId = $song->id;
        $this->editTitle = $song->title;
        $this->editArtist = $song->artist;
        $this->editEnergyLevel = $song->energy_level?->value;
        $this->editEra = $song->era;
        $this->editGenre = $song->genre;
        $this->editTheme = $song->theme;
        $this->editOriginalMusicalKey = $song->original_musical_key?->value;
        $this->editDurationInSeconds = $song->duration_in_seconds;
        $this->statusMessage = null;
    }

    public function cancelEditing(): void
    {
        $this->editingSongId = null;
        $this->resetValidation();
    }

    public function saveSong(): void
    {
        $this->validate([
            'editTitle' => ['required', 'string', 'max:255'],
            'editArtist' => ['required', 'string', 'max:255'],
            'editEnergyLevel' => ['nullable', 'string', 'in:low,medium,high'],
            'editEra' => ['nullable', 'in:' . implode(',', Era::labels())],
            'editGenre' => ['nullable', 'in:' . implode(',', Genre::labels())],
            'editTheme' => ['nullable', 'string', 'in:' . implode(',', SongTheme::values())],
            'editOriginalMusicalKey' => ['nullable', 'string', 'in:' . implode(',', array_map(fn ($k) => $k->value, MusicalKey::cases()))],
            'editDurationInSeconds' => ['nullable', 'integer', 'min:1', 'max:7200'],
        ]);

        $song = Song::findOrFail($this->editingSongId);

        $newNormalizedKey = Song::generateNormalizedKey($this->editTitle, $this->editArtist);

        if ($newNormalizedKey !== $song->normalized_key) {
            $existingSong = Song::where('normalized_key', $newNormalizedKey)
                ->where('id', '!=', $song->id)
                ->first();

            if ($existingSong) {
                $this->addError('editTitle', "A song with this title and artist already exists (ID: {$existingSong->id}).");

                return;
            }
        }

        $song->update([
            'title' => $this->editTitle,
            'artist' => $this->editArtist,
            'normalized_key' => $newNormalizedKey,
            'energy_level' => $this->editEnergyLevel !== '' ? $this->editEnergyLevel : null,
            'era' => $this->editEra !== '' ? $this->editEra : null,
            'genre' => $this->editGenre !== '' ? $this->editGenre : null,
            'theme' => $this->editTheme !== '' ? $this->editTheme : null,
            'original_musical_key' => $this->editOriginalMusicalKey !== '' ? $this->editOriginalMusicalKey : null,
            'duration_in_seconds' => $this->editDurationInSeconds,
        ]);

        $this->editingSongId = null;
        $this->statusMessage = "Updated \"{$song->title}\" by {$song->artist}.";

        unset($this->songs);
    }

    public function openMergeModal(int $songId): void
    {
        $song = Song::find($songId);
        if (! $song) {
            $this->errorMessage = 'Song not found — it may have already been deleted.';

            return;
        }

        $this->mergingFromSongId = $songId;
        $this->mergeTargetSearch = '';
        $this->mergeTargetSongId = null;
        $this->showMergeModal = true;
        $this->dispatch('open-modal', 'merge-song-confirm');
    }

    public function confirmDelete(int $songId): void
    {
        $song = Song::find($songId);
        if (! $song) {
            $this->errorMessage = 'Song not found — it may have already been deleted.';

            return;
        }

        $referenceCount = $song->projectSongs()->count();

        if ($referenceCount > 0) {
            $this->openMergeModal($songId);

            return;
        }

        $this->deletingSongId = $songId;
        $this->deleteReferencingProjectSongs = false;
        $this->showDeleteConfirm = true;
        $this->dispatch('open-modal', 'delete-song-confirm');
    }

    public function cancelDelete(): void
    {
        $this->deletingSongId = null;
        $this->deleteReferencingProjectSongs = false;
        $this->showDeleteConfirm = false;
        $this->dispatch('close-modal', 'delete-song-confirm');
    }

    public function deleteSong(): void
    {
        try {
            $song = Song::findOrFail($this->deletingSongId);

            $title = $song->title;
            $artist = $song->artist;

            $removedReferences = 0;

            DB::transaction(function () use ($song, &$removedReferences): void {
                if ($this->deleteReferencingProjectSongs) {
                    // Hard delete referencing project_songs first. Their
                    // charts, audio files, and performances cascade via FKs.
                    // Requests.song_id is nullOnDelete — we leave Request
                    // rows untouched so historical tip data is preserved,
                    // and only clear the pointer when the Song is deleted
                    // below.
                    $removedReferences = ProjectSong::where('song_id', $song->id)->count();
                    ProjectSong::where('song_id', $song->id)->delete();
                }

                $song->delete();
            });

            if ($this->deleteReferencingProjectSongs) {
                $this->statusMessage = "Deleted \"{$title}\" by {$artist} along with {$removedReferences} referencing repertoire "
                    .Str::plural('song', $removedReferences).'.';
            } else {
                $this->statusMessage = "Deleted \"{$title}\" by {$artist}. Existing project songs and charts are preserved.";
            }
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to delete song: '.$e->getMessage();
        }

        $this->showDeleteConfirm = false;
        $this->deletingSongId = null;
        $this->deleteReferencingProjectSongs = false;
        $this->dispatch('close-modal', 'delete-song-confirm');

        unset($this->songs);
        unset($this->totalSongs);
        unset($this->duplicateCount);
        unset($this->incompleteMetadataCount);
    }

    public function cancelMerge(): void
    {
        $this->mergingFromSongId = null;
        $this->mergeTargetSearch = '';
        $this->mergeTargetSongId = null;
        $this->showMergeModal = false;
        $this->dispatch('close-modal', 'merge-song-confirm');
    }

    public function selectMergeTarget(int $songId): void
    {
        $this->mergeTargetSongId = $songId;
        unset($this->mergeTargetSong);
    }

    #[Computed]
    public function mergingFromSong(): ?Song
    {
        return $this->mergingFromSongId ? Song::find($this->mergingFromSongId) : null;
    }

    #[Computed]
    public function mergeTargetResults(): array
    {
        if ($this->mergeTargetSearch === '') {
            return [];
        }

        $search = $this->mergeTargetSearch;

        return Song::query()
            ->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('artist', 'like', "%{$search}%");
            })
            ->where('id', '!=', $this->mergingFromSongId)
            ->orderBy('title')
            ->limit(10)
            ->get()
            ->all();
    }

    #[Computed]
    public function mergeTargetSong(): ?Song
    {
        return $this->mergeTargetSongId ? Song::find($this->mergeTargetSongId) : null;
    }

    #[Computed]
    public function mergingFromReferenceCount(): int
    {
        return $this->mergingFromSongId
            ? ProjectSong::where('song_id', $this->mergingFromSongId)->count()
            : 0;
    }

    public function mergeAndDelete(): void
    {
        if (! $this->mergingFromSongId || ! $this->mergeTargetSongId) {
            return;
        }

        try {
            $fromSong = Song::findOrFail($this->mergingFromSongId);
            $targetSong = Song::findOrFail($this->mergeTargetSongId);

            DB::transaction(function () use ($fromSong, $targetSong): void {
                // Migrate project songs — merge into existing or reassign
                $existingTargetProjectSongs = ProjectSong::where('song_id', $targetSong->id)
                    ->get()
                    ->keyBy(fn (ProjectSong $ps) => $ps->project_id.'|'.$ps->version_label);

                foreach (ProjectSong::where('song_id', $fromSong->id)->get() as $projectSong) {
                    $key = $projectSong->project_id.'|'.$projectSong->version_label;

                    if ($existingTargetProjectSongs->has($key)) {
                        // Target already has this song in the same project+version — reassign children
                        $targetPs = $existingTargetProjectSongs->get($key);
                        $projectSong->charts()->update(['project_song_id' => $targetPs->id]);
                        $projectSong->audioFiles()->update(['project_song_id' => $targetPs->id]);
                        $projectSong->delete();
                    } else {
                        $projectSong->update(['song_id' => $targetSong->id]);
                    }
                }

                // Migrate direct song references
                Chart::where('song_id', $fromSong->id)->update(['song_id' => $targetSong->id]);
                Request::where('song_id', $fromSong->id)->update(['song_id' => $targetSong->id]);
                SongIntegrityIssue::where('song_id', $fromSong->id)->delete();

                $fromSong->merged_into_song_id = $targetSong->id;
                $fromSong->save();
                $fromSong->delete();
            });

            $this->statusMessage = "Merged \"{$fromSong->title}\" by {$fromSong->artist} into \"{$targetSong->title}\" by {$targetSong->artist} and deleted the duplicate.";
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to merge songs: '.$e->getMessage();
        }

        $this->showMergeModal = false;
        $this->mergingFromSongId = null;
        $this->mergeTargetSearch = '';
        $this->mergeTargetSongId = null;
        $this->dispatch('close-modal', 'merge-song-confirm');

        unset($this->songs);
        unset($this->totalSongs);
        unset($this->duplicateCount);
        unset($this->incompleteMetadataCount);
        unset($this->mergingFromSong);
        unset($this->mergeTargetResults);
        unset($this->mergeTargetSong);
        unset($this->mergingFromReferenceCount);
    }

    public function forceDeleteFromMergeModal(): void
    {
        $this->forceDeleteFromMergeModalInternal(deleteReferences: false);
    }

    public function forceDeleteWithReferencesFromMergeModal(): void
    {
        $this->forceDeleteFromMergeModalInternal(deleteReferences: true);
    }

    private function forceDeleteFromMergeModalInternal(bool $deleteReferences): void
    {
        $this->dispatch('close-modal', 'merge-song-confirm');
        $this->showMergeModal = false;

        $this->deletingSongId = $this->mergingFromSongId;
        $this->deleteReferencingProjectSongs = $deleteReferences;
        $this->mergingFromSongId = null;
        $this->mergeTargetSearch = '';
        $this->mergeTargetSongId = null;

        $this->showDeleteConfirm = true;
        $this->dispatch('open-modal', 'delete-song-confirm');
    }

    public function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return "\xe2\x80\x94";
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
};
?>

<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        {{-- Stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <button wire:click="$set('view', 'all')" class="rounded-2xl border border-ink-border/80 bg-surface/95 p-5 text-left shadow-sm transition hover:border-brand/50 dark:border-ink-border-dark/80 dark:bg-surface-inverse/90 {{ $view === 'all' ? 'ring-2 ring-brand/40' : '' }}">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Total Master Songs</p>
                <p class="mt-2 text-3xl font-bold text-ink dark:text-ink-inverse">{{ number_format($this->totalSongs) }}</p>
            </button>
            <button wire:click="$set('view', 'duplicates')" class="rounded-2xl border border-ink-border/80 bg-surface/95 p-5 text-left shadow-sm transition hover:border-brand/50 dark:border-ink-border-dark/80 dark:bg-surface-inverse/90 {{ $view === 'duplicates' ? 'ring-2 ring-brand/40' : '' }}" data-test="view-duplicates">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Duplicates</p>
                <p class="mt-2 text-3xl font-bold {{ $this->duplicateCount > 0 ? 'text-amber-500' : 'text-success-500' }}">{{ number_format($this->duplicateCount) }}</p>
            </button>
            <div class="rounded-2xl border border-ink-border/80 bg-surface/95 p-5 shadow-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Incomplete Metadata</p>
                <p class="mt-2 text-3xl font-bold {{ $this->incompleteMetadataCount > 0 ? 'text-ink-soft' : 'text-success-500' }}">{{ number_format($this->incompleteMetadataCount) }}</p>
            </div>
        </div>

        {{-- Status Message --}}
        @if ($statusMessage)
            <div class="rounded-xl border border-success-100 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-700/60 dark:bg-success-900/20 dark:text-success-200" data-test="admin-songs-status">
                {{ $statusMessage }}
            </div>
        @endif

        {{-- Error Message --}}
        @if ($errorMessage)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-700/60 dark:bg-red-900/20 dark:text-red-200" data-test="admin-songs-error">
                {{ $errorMessage }}
            </div>
        @endif

        {{-- Search + Sort --}}
        <div class="rounded-2xl border border-ink-border/80 bg-surface/95 shadow-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
            <div class="border-b border-ink-border px-4 py-5 sm:px-6 dark:border-ink-border-dark">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Master Song Data</h3>
                        <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
                            Search, view, and edit global song metadata. Changes here affect all projects referencing these songs.
                        </p>
                    </div>
                    <div class="w-full sm:w-80">
                        <x-text-input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search by title or artist..."
                            class="w-full"
                            data-test="admin-songs-search"
                        />
                    </div>
                </div>
                {{-- Sort controls for mobile --}}
                <div class="mt-3 flex flex-wrap gap-2 md:hidden">
                    <span class="self-center text-xs font-medium text-ink-muted dark:text-ink-soft">Sort:</span>
                    @foreach (['title' => 'Title', 'artist' => 'Artist', 'updated_at' => 'Updated'] as $field => $label)
                        <button
                            wire:click="sortBy('{{ $field }}')"
                            class="rounded-lg px-2.5 py-1 text-xs font-medium transition
                                {{ $sortField === $field
                                    ? 'bg-brand/20 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300'
                                    : 'bg-surface-muted text-ink-muted hover:text-ink dark:bg-surface-elevated dark:text-ink-soft dark:hover:text-ink-inverse' }}"
                        >
                            {{ $label }}
                            @if ($sortField === $field)
                                {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Mobile card list --}}
            <div class="divide-y divide-ink-border md:hidden dark:divide-ink-border-dark">
                @forelse ($this->songs as $song)
                    @if ($editingSongId === $song->id)
                        {{-- Edit form --}}
                        <div wire:key="song-{{ $song->id }}" class="bg-brand/5 px-4 py-4 dark:bg-brand-900/10">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Title</label>
                                    <x-text-input wire:model="editTitle" class="w-full" data-test="edit-title" />
                                    @error('editTitle') <p class="mt-1 text-xs text-red-600 dark:text-red-300">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Artist</label>
                                    <x-text-input wire:model="editArtist" class="w-full" data-test="edit-artist" />
                                    @error('editArtist') <p class="mt-1 text-xs text-red-600 dark:text-red-300">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Energy</label>
                                    <x-select-input wire:model="editEnergyLevel" class="w-full" data-test="edit-energy">
                                        <option value="">--</option>
                                        @foreach (EnergyLevel::cases() as $level)
                                            <option value="{{ $level->value }}">{{ ucfirst($level->value) }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Era</label>
                                    <x-select-input wire:model="editEra" class="w-full" data-test="edit-era">
                                        <option value="">--</option>
                                        @foreach (Era::cases() as $era)
                                            <option value="{{ $era->value }}">{{ $era->value }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Genre</label>
                                    <x-select-input wire:model="editGenre" class="w-full" data-test="edit-genre">
                                        <option value="">--</option>
                                        @foreach (Genre::cases() as $genre)
                                            <option value="{{ $genre->value }}">{{ $genre->value }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Theme</label>
                                    <x-select-input wire:model="editTheme" class="w-full" data-test="edit-theme">
                                        <option value="">--</option>
                                        @foreach (SongTheme::cases() as $theme)
                                            <option value="{{ $theme->value }}">{{ $theme->label() }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Key</label>
                                    <x-select-input wire:model="editOriginalMusicalKey" class="w-full" data-test="edit-key">
                                        <option value="">--</option>
                                        @foreach (MusicalKey::cases() as $key)
                                            <option value="{{ $key->value }}">{{ $key->value }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Duration (seconds)</label>
                                    <x-text-input type="number" wire:model="editDurationInSeconds" class="w-full" placeholder="sec" min="1" max="7200" data-test="edit-duration" />
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-end gap-2">
                                <x-secondary-button wire:click="cancelEditing" type="button" class="px-3 py-1.5 text-xs">Cancel</x-secondary-button>
                                <x-primary-button wire:click="saveSong" type="button" class="px-3 py-1.5 text-xs" data-test="save-song">Save</x-primary-button>
                            </div>
                        </div>
                    @else
                        {{-- Display card --}}
                        <div wire:key="song-{{ $song->id }}" class="px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-ink dark:text-ink-inverse">{{ $song->title }}</p>
                                    <p class="text-sm text-ink-muted dark:text-ink-soft">
                                        {{ $song->artist }}
                                        @if ($view === 'duplicates' && isset($song->project_songs_count))
                                            <span class="ml-2 text-xs">({{ $song->project_songs_count }} {{ Str::plural('ref', $song->project_songs_count) }})</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        wire:click="startEditing({{ $song->id }})"
                                        class="rounded-lg p-1.5 text-ink-muted transition hover:bg-surface-muted hover:text-ink dark:text-ink-soft dark:hover:bg-surface-elevated dark:hover:text-ink-inverse"
                                        title="Edit song"
                                        data-test="edit-song-{{ $song->id }}"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button
                                        wire:click="openMergeModal({{ $song->id }})"
                                        class="rounded-lg p-1.5 text-ink-muted transition hover:bg-amber-50 hover:text-amber-600 dark:text-ink-soft dark:hover:bg-amber-900/30 dark:hover:text-amber-300"
                                        title="Merge into another song"
                                        data-test="merge-song-{{ $song->id }}"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    </button>
                                    <button
                                        wire:click="confirmDelete({{ $song->id }})"
                                        class="rounded-lg p-1.5 text-ink-muted transition hover:bg-red-50 hover:text-red-600 dark:text-ink-soft dark:hover:bg-red-900/30 dark:hover:text-red-300"
                                        title="Delete song"
                                        data-test="delete-song-{{ $song->id }}"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-ink-muted dark:text-ink-soft">
                                @if ($song->energy_level)
                                    <span class="inline-flex rounded-full px-2 py-0.5 font-medium
                                        {{ match($song->energy_level) {
                                            EnergyLevel::Low => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                            EnergyLevel::Medium => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                            EnergyLevel::High => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                        } }}">{{ ucfirst($song->energy_level->value) }}</span>
                                @endif
                                @if ($song->era)<span>{{ $song->era }}</span>@endif
                                @if ($song->genre)<span>{{ $song->genre }}</span>@endif
                                @if ($song->theme)<span>{{ SongTheme::tryFrom($song->theme)?->label() ?? $song->theme }}</span>@endif
                                @if ($song->original_musical_key)<span>{{ $song->original_musical_key->value }}</span>@endif
                                @if ($song->duration_in_seconds)<span>{{ $this->formatDuration($song->duration_in_seconds) }}</span>@endif
                                <span class="text-ink-muted/60 dark:text-ink-soft/60">{{ $song->updated_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="px-4 py-12 text-center text-ink-muted dark:text-ink-soft">
                        @if ($search !== '')
                            No songs matching "{{ $search }}".
                        @else
                            No songs in the master database yet.
                        @endif
                    </div>
                @endforelse
            </div>

            {{-- Desktop table --}}
            <div class="hidden md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-border bg-surface-muted text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:border-ink-border-dark dark:bg-surface-elevated dark:text-ink-soft">
                        <tr>
                            <th class="px-4 py-3">
                                <button wire:click="sortBy('title')" class="inline-flex items-center gap-1 hover:text-ink dark:hover:text-ink-inverse">
                                    Title @if ($sortField === 'title')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                                </button>
                            </th>
                            <th class="px-4 py-3">
                                <button wire:click="sortBy('artist')" class="inline-flex items-center gap-1 hover:text-ink dark:hover:text-ink-inverse">
                                    Artist @if ($sortField === 'artist')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                                </button>
                            </th>
                            <th class="px-4 py-3">Energy</th>
                            <th class="px-4 py-3">Era</th>
                            <th class="hidden px-4 py-3 lg:table-cell">Genre</th>
                            <th class="hidden px-4 py-3 lg:table-cell">Theme</th>
                            <th class="hidden px-4 py-3 lg:table-cell">Key</th>
                            <th class="hidden px-4 py-3 lg:table-cell">Duration</th>
                            @if ($view === 'duplicates')
                                <th class="px-4 py-3">Refs</th>
                            @endif
                            <th class="px-4 py-3">
                                <button wire:click="sortBy('updated_at')" class="inline-flex items-center gap-1 hover:text-ink dark:hover:text-ink-inverse">
                                    Updated @if ($sortField === 'updated_at')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-border dark:divide-ink-border-dark">
                        @forelse ($this->songs as $song)
                            @if ($editingSongId === $song->id)
                                <tr wire:key="song-{{ $song->id }}" class="bg-brand/5 dark:bg-brand-900/10">
                                    <td colspan="{{ $view === 'duplicates' ? 11 : 10 }}" class="px-4 py-4">
                                        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Title</label>
                                                <x-text-input wire:model="editTitle" class="w-full" data-test="edit-title" />
                                                @error('editTitle') <p class="mt-1 text-xs text-red-600 dark:text-red-300">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Artist</label>
                                                <x-text-input wire:model="editArtist" class="w-full" data-test="edit-artist" />
                                                @error('editArtist') <p class="mt-1 text-xs text-red-600 dark:text-red-300">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Energy</label>
                                                <x-select-input wire:model="editEnergyLevel" class="w-full" data-test="edit-energy">
                                                    <option value="">--</option>
                                                    @foreach (EnergyLevel::cases() as $level)
                                                        <option value="{{ $level->value }}">{{ ucfirst($level->value) }}</option>
                                                    @endforeach
                                                </x-select-input>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Era</label>
                                                <x-select-input wire:model="editEra" class="w-full" data-test="edit-era">
                                                    <option value="">--</option>
                                                    @foreach (Era::cases() as $era)
                                                        <option value="{{ $era->value }}">{{ $era->value }}</option>
                                                    @endforeach
                                                </x-select-input>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Genre</label>
                                                <x-select-input wire:model="editGenre" class="w-full" data-test="edit-genre">
                                                    <option value="">--</option>
                                                    @foreach (Genre::cases() as $genre)
                                                        <option value="{{ $genre->value }}">{{ $genre->value }}</option>
                                                    @endforeach
                                                </x-select-input>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Theme</label>
                                                <x-select-input wire:model="editTheme" class="w-full" data-test="edit-theme">
                                                    <option value="">--</option>
                                                    @foreach (SongTheme::cases() as $theme)
                                                        <option value="{{ $theme->value }}">{{ $theme->label() }}</option>
                                                    @endforeach
                                                </x-select-input>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Key</label>
                                                <x-select-input wire:model="editOriginalMusicalKey" class="w-full" data-test="edit-key">
                                                    <option value="">--</option>
                                                    @foreach (MusicalKey::cases() as $key)
                                                        <option value="{{ $key->value }}">{{ $key->value }}</option>
                                                    @endforeach
                                                </x-select-input>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Duration (seconds)</label>
                                                <x-text-input type="number" wire:model="editDurationInSeconds" class="w-full" placeholder="sec" min="1" max="7200" data-test="edit-duration" />
                                            </div>
                                        </div>
                                        <div class="mt-4 flex items-center justify-end gap-2">
                                            <x-secondary-button wire:click="cancelEditing" type="button" class="px-3 py-1.5 text-xs">Cancel</x-secondary-button>
                                            <x-primary-button wire:click="saveSong" type="button" class="px-3 py-1.5 text-xs" data-test="save-song">Save</x-primary-button>
                                        </div>
                                    </td>
                                </tr>
                            @else
                                <tr wire:key="song-{{ $song->id }}" class="transition hover:bg-surface-muted dark:hover:bg-surface-elevated">
                                    <td class="px-4 py-3 font-medium text-ink dark:text-ink-inverse">{{ $song->title }}</td>
                                    <td class="px-4 py-3 text-ink-muted dark:text-ink-soft">{{ $song->artist }}</td>
                                    <td class="px-4 py-3">
                                        @if ($song->energy_level)
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                                {{ match($song->energy_level) {
                                                    EnergyLevel::Low => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                                    EnergyLevel::Medium => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                                    EnergyLevel::High => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                                } }}">{{ ucfirst($song->energy_level->value) }}</span>
                                        @else
                                            <span class="text-ink-muted/50 dark:text-ink-soft/50">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-ink-muted dark:text-ink-soft">{{ $song->era ?? '—' }}</td>
                                    <td class="hidden px-4 py-3 text-ink-muted lg:table-cell dark:text-ink-soft">{{ $song->genre ?? '—' }}</td>
                                    <td class="hidden px-4 py-3 lg:table-cell">
                                        @if ($song->theme)
                                            <span class="text-ink-muted dark:text-ink-soft">{{ SongTheme::tryFrom($song->theme)?->label() ?? $song->theme }}</span>
                                        @else
                                            <span class="text-ink-muted/50 dark:text-ink-soft/50">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="hidden px-4 py-3 text-ink-muted lg:table-cell dark:text-ink-soft">{{ $song->original_musical_key?->value ?? '—' }}</td>
                                    <td class="hidden px-4 py-3 text-ink-muted lg:table-cell dark:text-ink-soft">{{ $this->formatDuration($song->duration_in_seconds) }}</td>
                                    @if ($view === 'duplicates')
                                        <td class="px-4 py-3 text-ink-muted dark:text-ink-soft">{{ $song->project_songs_count ?? 0 }}</td>
                                    @endif
                                    <td class="px-4 py-3 text-xs text-ink-muted dark:text-ink-soft">{{ $song->updated_at->diffForHumans() }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                wire:click="startEditing({{ $song->id }})"
                                                class="rounded-lg p-1.5 text-ink-muted transition hover:bg-surface-muted hover:text-ink dark:text-ink-soft dark:hover:bg-surface-elevated dark:hover:text-ink-inverse"
                                                title="Edit song"
                                                data-test="edit-song-{{ $song->id }}"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button
                                                wire:click="openMergeModal({{ $song->id }})"
                                                class="rounded-lg p-1.5 text-ink-muted transition hover:bg-amber-50 hover:text-amber-600 dark:text-ink-soft dark:hover:bg-amber-900/30 dark:hover:text-amber-300"
                                                title="Merge into another song"
                                                data-test="merge-song-{{ $song->id }}"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                            </button>
                                            <button
                                                wire:click="confirmDelete({{ $song->id }})"
                                                class="rounded-lg p-1.5 text-ink-muted transition hover:bg-red-50 hover:text-red-600 dark:text-ink-soft dark:hover:bg-red-900/30 dark:hover:text-red-300"
                                                title="Delete song"
                                                data-test="delete-song-{{ $song->id }}"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $view === 'duplicates' ? 11 : 10 }}" class="px-4 py-12 text-center text-ink-muted dark:text-ink-soft">
                                    @if ($search !== '')
                                        No songs matching "{{ $search }}".
                                    @else
                                        No songs in the master database yet.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($this->songs->hasPages())
                <div class="border-t border-ink-border px-4 py-4 sm:px-6 dark:border-ink-border-dark">
                    {{ $this->songs->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <x-modal name="delete-song-confirm" :show="$showDeleteConfirm" maxWidth="md">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Delete Master Song</h3>
            @if ($deleteReferencingProjectSongs)
                <p class="mt-2 text-sm text-ink-muted dark:text-ink-soft">
                    This will remove the song from the master database <strong class="text-red-600 dark:text-red-400">AND permanently delete every referencing repertoire song</strong>, along with their charts, audio files, and performance history. This cannot be undone.
                </p>
            @else
                <p class="mt-2 text-sm text-ink-muted dark:text-ink-soft">
                    This will remove the song from the master database so it can no longer be linked to new project songs. Existing project songs and their charts will be preserved.
                </p>
            @endif
            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button wire:click="cancelDelete" type="button">Cancel</x-secondary-button>
                <x-danger-button wire:click="deleteSong" type="button" data-test="confirm-delete-song">Delete</x-danger-button>
            </div>
        </div>
    </x-modal>

    {{-- Merge & Delete Modal --}}
    <x-modal name="merge-song-confirm" :show="$showMergeModal" maxWidth="lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Song Has Project References</h3>

            @if ($this->mergingFromSong)
                <p class="mt-2 text-sm text-ink-muted dark:text-ink-soft">
                    <strong class="text-ink dark:text-ink-inverse">"{{ $this->mergingFromSong->title }}"</strong> by {{ $this->mergingFromSong->artist }}
                    is referenced by <strong>{{ $this->mergingFromReferenceCount }}</strong> project {{ Str::plural('song', $this->mergingFromReferenceCount) }}.
                    You can merge these references into another song before deleting.
                </p>

                {{-- Target search --}}
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-medium text-ink-muted dark:text-ink-soft">Search for target song</label>
                    <x-text-input
                        type="search"
                        wire:model.live.debounce.300ms="mergeTargetSearch"
                        placeholder="Search by title or artist..."
                        class="w-full"
                        data-test="merge-target-search"
                    />
                </div>

                {{-- Search results --}}
                @if (count($this->mergeTargetResults) > 0)
                    <ul class="mt-2 max-h-48 divide-y divide-ink-border overflow-y-auto rounded-lg border border-ink-border dark:divide-ink-border-dark dark:border-ink-border-dark">
                        @foreach ($this->mergeTargetResults as $result)
                            <li>
                                <button
                                    wire:click="selectMergeTarget({{ $result->id }})"
                                    type="button"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-surface-muted dark:hover:bg-surface-elevated {{ $mergeTargetSongId === $result->id ? 'bg-brand/10 dark:bg-brand-900/20' : '' }}"
                                    data-test="merge-target-{{ $result->id }}"
                                >
                                    <div>
                                        <span class="font-medium text-ink dark:text-ink-inverse">{{ $result->title }}</span>
                                        <span class="text-ink-muted dark:text-ink-soft">&mdash; {{ $result->artist }}</span>
                                    </div>
                                    @if ($mergeTargetSongId === $result->id)
                                        <svg class="h-4 w-4 shrink-0 text-brand" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @elseif ($mergeTargetSearch !== '')
                    <p class="mt-2 text-sm text-ink-muted dark:text-ink-soft">No matching songs found.</p>
                @endif

                {{-- Selected target confirmation --}}
                @if ($this->mergeTargetSong)
                    <div class="mt-3 rounded-lg border border-brand/30 bg-brand/5 px-3 py-2 text-sm dark:border-brand-700/40 dark:bg-brand-900/10">
                        Merge into: <strong class="text-ink dark:text-ink-inverse">"{{ $this->mergeTargetSong->title }}"</strong> by {{ $this->mergeTargetSong->artist }}
                    </div>
                @endif
            @endif

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-1 text-left">
                    <button
                        wire:click="forceDeleteFromMergeModal"
                        type="button"
                        class="text-sm text-ink-muted underline transition hover:text-red-600 dark:text-ink-soft dark:hover:text-red-300"
                        data-test="force-delete-preserve-references"
                    >
                        Delete without merging (preserve {{ $this->mergingFromReferenceCount }} {{ Str::plural('reference', $this->mergingFromReferenceCount) }})
                    </button>
                    <button
                        wire:click="forceDeleteWithReferencesFromMergeModal"
                        type="button"
                        class="text-sm text-red-600 underline transition hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                        data-test="force-delete-remove-references"
                    >
                        Delete AND remove {{ $this->mergingFromReferenceCount }} referencing {{ Str::plural('repertoire song', $this->mergingFromReferenceCount) }}
                    </button>
                </div>
                <div class="flex gap-3">
                    <x-secondary-button wire:click="cancelMerge" type="button">Cancel</x-secondary-button>
                    <x-danger-button
                        wire:click="mergeAndDelete"
                        type="button"
                        data-test="confirm-merge-song"
                        :disabled="! $mergeTargetSongId"
                        @class(['opacity-50 cursor-not-allowed' => ! $mergeTargetSongId])
                    >
                        Merge & Delete
                    </x-danger-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
