<?php

use App\Models\Project;
use App\Support\TipAmount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $projectId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    public string $slug = '';

    #[Validate('required|numeric|min:0|max:1000')]
    public float $minTipDollars = 5.00;

    #[Validate('required|numeric|min:1|max:1000')]
    public float $quickTip1Dollars = 20.00;

    #[Validate('required|numeric|min:1|max:1000')]
    public float $quickTip2Dollars = 15.00;

    #[Validate('required|numeric|min:1|max:1000')]
    public float $quickTip3Dollars = 10.00;

    #[Validate('nullable|url|max:2048')]
    public string $performerInfoUrl = '';

    public bool $isAcceptingRequests = true;

    public bool $isAcceptingTips = true;

    public bool $isAcceptingOriginalRequests = true;

    #[Validate('nullable|image|max:5120')]
    public $performerProfileImage = null;

    public ?string $existingPerformerProfileImageUrl = null;

    public bool $removePerformerProfileImage = false;

    public bool $showForm = false;

    public bool $slugManuallyEdited = false;

    public string $originalSlug = '';

    public ?int $publicRepertoireSetId = null;

    public ?string $publicRepertoireSetLabel = null;

    #[Validate('required|integer|min:1|max:100')]
    public int $minSuggestedSetlistSongs = 5;

    #[Validate('required|integer|min:1|max:100')]
    public int $maxSuggestedSetlistSongs = 25;

    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('projects', 'slug')->ignore($this->projectId),
            ],
        ];
    }

    #[On('edit')]
    public function edit(int $projectId): void
    {
        $project = Project::findOrFail($projectId);

        if (! $project->isOwnedBy(Auth::user())) {
            abort(403);
        }

        $this->projectId = $project->id;
        $this->name = $project->name;
        $this->slug = $project->slug;
        $this->originalSlug = $project->slug;
        $this->minTipDollars = TipAmount::wholeDollarAmount($project->min_tip_cents);
        $this->quickTip1Dollars = TipAmount::wholeDollarAmount($project->quick_tip_amounts_cents[0]);
        $this->quickTip2Dollars = TipAmount::wholeDollarAmount($project->quick_tip_amounts_cents[1]);
        $this->quickTip3Dollars = TipAmount::wholeDollarAmount($project->quick_tip_amounts_cents[2]);
        $this->performerInfoUrl = $project->performer_info_url ?? '';
        $this->isAcceptingRequests = $project->is_accepting_requests;
        $this->isAcceptingTips = $project->is_accepting_tips;
        $this->isAcceptingOriginalRequests = $project->is_accepting_original_requests;
        $this->existingPerformerProfileImageUrl = $project->performer_profile_image_url;
        $this->performerProfileImage = null;
        $this->removePerformerProfileImage = false;
        $this->slugManuallyEdited = true;
        $this->publicRepertoireSetId = $project->public_repertoire_set_id;
        if ($this->publicRepertoireSetId !== null) {
            $project->load('publicRepertoireSet.setlist');
            $set = $project->publicRepertoireSet;
            $this->publicRepertoireSetLabel = $set
                ? $set->name . ($set->setlist ? ' from ' . $set->setlist->name : '')
                : 'Custom set';
        } else {
            $this->publicRepertoireSetLabel = null;
        }
        $this->minSuggestedSetlistSongs = $project->min_suggested_setlist_songs ?? 5;
        $this->maxSuggestedSetlistSongs = $project->max_suggested_setlist_songs ?? 25;
        $this->showForm = true;
    }

    public function updatedName(string $value): void
    {
        if (! $this->slugManuallyEdited) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(string $value): void
    {
        $this->slugManuallyEdited = true;
        $this->slug = Str::slug($value);
    }

    public function updatedMinTipDollars($value): void
    {
        $this->minTipDollars = $this->normalizeWholeDollarInput($value);
    }

    public function updatedQuickTip1Dollars($value): void
    {
        $this->quickTip1Dollars = $this->normalizeWholeDollarInput($value);
    }

    public function updatedQuickTip2Dollars($value): void
    {
        $this->quickTip2Dollars = $this->normalizeWholeDollarInput($value);
    }

    public function updatedQuickTip3Dollars($value): void
    {
        $this->quickTip3Dollars = $this->normalizeWholeDollarInput($value);
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset([
            'projectId',
            'name',
            'slug',
            'minTipDollars',
            'quickTip1Dollars',
            'quickTip2Dollars',
            'quickTip3Dollars',
            'performerInfoUrl',
            'isAcceptingRequests',
            'isAcceptingTips',
            'isAcceptingOriginalRequests',
            'performerProfileImage',
            'existingPerformerProfileImageUrl',
            'removePerformerProfileImage',
            'slugManuallyEdited',
            'originalSlug',
        ]);
        $this->minTipDollars = 5.00;
        $this->quickTip1Dollars = 20.00;
        $this->quickTip2Dollars = 15.00;
        $this->quickTip3Dollars = 10.00;
        $this->isAcceptingRequests = true;
        $this->isAcceptingTips = true;
        $this->isAcceptingOriginalRequests = true;
        $this->minSuggestedSetlistSongs = 5;
        $this->maxSuggestedSetlistSongs = 25;
        $this->resetValidation();
    }

    public function resetPublicSongList(): void
    {
        $project = Project::findOrFail($this->projectId);

        if (! $project->isOwnedBy(Auth::user())) {
            abort(403);
        }

        $project->update(['public_repertoire_set_id' => null]);
        $this->publicRepertoireSetId = null;
        $this->publicRepertoireSetLabel = null;
    }

    public function update(): void
    {
        $this->validate();
        $quickTipAmounts = $this->validatedQuickTipAmounts();
        if ($quickTipAmounts === null) {
            return;
        }

        if ($this->minSuggestedSetlistSongs > $this->maxSuggestedSetlistSongs) {
            $this->addError('minSuggestedSetlistSongs', 'Minimum songs must not exceed maximum songs.');

            return;
        }

        $project = Project::findOrFail($this->projectId);

        if (! $project->isOwnedBy(Auth::user())) {
            abort(403);
        }

        $profileImagePath = $project->performer_profile_image_path;
        if ($this->removePerformerProfileImage && $profileImagePath !== null) {
            Storage::disk('public')->delete($profileImagePath);
            $profileImagePath = null;
        }

        if ($this->performerProfileImage !== null) {
            if ($profileImagePath !== null) {
                Storage::disk('public')->delete($profileImagePath);
            }
            $profileImagePath = $this->performerProfileImage->store(
                "performers/{$project->id}",
                'public'
            );
        }

        $project->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'performer_info_url' => $this->performerInfoUrl !== '' ? $this->performerInfoUrl : null,
            'performer_profile_image_path' => $profileImagePath,
            'min_tip_cents' => TipAmount::normalizeCents(
                (int) round($this->minTipDollars * 100)
            ),
            ...Project::quickTipAttributes($quickTipAmounts),
            'is_accepting_requests' => $this->isAcceptingRequests,
            'is_accepting_tips' => $this->isAcceptingTips,
            'is_accepting_original_requests' => $this->isAcceptingOriginalRequests,
            'min_suggested_setlist_songs' => $this->minSuggestedSetlistSongs,
            'max_suggested_setlist_songs' => $this->maxSuggestedSetlistSongs,
        ]);

        $this->cancel();

        $this->dispatch('project-updated', projectId: $project->id);
    }

    private function normalizeWholeDollarInput($value): float
    {
        $numericValue = (float) $value;

        if ($numericValue < 0) {
            return $numericValue;
        }

        return (float) TipAmount::wholeDollarAmount(
            (int) round($numericValue * 100)
        );
    }

    /**
     * @return array<int, int>|null
     */
    private function validatedQuickTipAmounts(): ?array
    {
        $this->resetValidation([
            'quickTip1Dollars',
            'quickTip2Dollars',
            'quickTip3Dollars',
        ]);

        $amounts = [
            TipAmount::normalizeCents((int) round($this->quickTip1Dollars * 100)),
            TipAmount::normalizeCents((int) round($this->quickTip2Dollars * 100)),
            TipAmount::normalizeCents((int) round($this->quickTip3Dollars * 100)),
        ];

        if (count(array_unique($amounts)) !== 3) {
            $message = 'Quick tip buttons must all be different.';
            $this->addError('quickTip1Dollars', $message);
            $this->addError('quickTip2Dollars', $message);
            $this->addError('quickTip3Dollars', $message);

            return null;
        }

        if (! ($amounts[0] > $amounts[1] && $amounts[1] > $amounts[2])) {
            $message = 'Quick tip buttons must be saved highest to lowest.';
            $this->addError('quickTip1Dollars', $message);
            $this->addError('quickTip2Dollars', $message);
            $this->addError('quickTip3Dollars', $message);

            return null;
        }

        return $amounts;
    }
};
?>

<div>
    @if ($showForm)
        <div class="fixed inset-0 z-40 bg-canvas-dark/70 backdrop-blur-sm" wire:click="cancel"></div>
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <x-ui.panel class="relative overflow-hidden text-left transition-all sm:my-8 sm:w-full sm:max-w-2xl" wire:click.stop>
                    <form wire:submit="update">
                        <div class="border-b border-ink-border px-6 py-5 dark:border-ink-border-dark">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">Edit Project</h3>
                                <button
                                    type="button"
                                    wire:click="cancel"
                                    class="text-ink-muted transition hover:text-ink dark:text-ink-soft dark:hover:text-ink-inverse"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="px-6 py-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="edit-name" class="mb-1 block text-sm font-medium text-ink dark:text-ink-inverse">
                                        Project Name
                                    </label>
                                    <x-text-input
                                        type="text"
                                        id="edit-name"
                                        wire:model.live.debounce.300ms="name"
                                        class="w-full"
                                        placeholder="My Band Name"
                                    />
                                    @error('name')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="edit-minTipDollars" class="mb-1 block text-sm font-medium text-ink dark:text-ink-inverse">
                                        Minimum Tip Amount
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-ink-muted dark:text-ink-soft">$</span>
                                        </div>
                                        <x-text-input
                                            type="number"
                                            id="edit-minTipDollars"
                                            wire:model="minTipDollars"
                                            step="1"
                                            min="0"
                                            max="1000"
                                            class="w-full pl-7"
                                        />
                                    </div>
                                    @error('minTipDollars')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-ink-muted dark:text-ink-soft">
                                        Ignored whenever tips are turned off.
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-3 block text-sm font-medium text-ink dark:text-ink-inverse">
                                        Audience Quick Tip Buttons
                                    </label>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div>
                                            <label for="edit-quickTip1Dollars" class="mb-1 block text-xs font-medium uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                                                Top Button
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-ink-muted dark:text-ink-soft">$</span>
                                                </div>
                                                <x-text-input
                                                    type="number"
                                                    id="edit-quickTip1Dollars"
                                                    wire:model="quickTip1Dollars"
                                                    step="1"
                                                    min="1"
                                                    max="1000"
                                                    class="w-full pl-7"
                                                />
                                            </div>
                                            @error('quickTip1Dollars')
                                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="edit-quickTip2Dollars" class="mb-1 block text-xs font-medium uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                                                Middle Button
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-ink-muted dark:text-ink-soft">$</span>
                                                </div>
                                                <x-text-input
                                                    type="number"
                                                    id="edit-quickTip2Dollars"
                                                    wire:model="quickTip2Dollars"
                                                    step="1"
                                                    min="1"
                                                    max="1000"
                                                    class="w-full pl-7"
                                                />
                                            </div>
                                            @error('quickTip2Dollars')
                                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="edit-quickTip3Dollars" class="mb-1 block text-xs font-medium uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                                                Bottom Button
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-ink-muted dark:text-ink-soft">$</span>
                                                </div>
                                                <x-text-input
                                                    type="number"
                                                    id="edit-quickTip3Dollars"
                                                    wire:model="quickTip3Dollars"
                                                    step="1"
                                                    min="1"
                                                    max="1000"
                                                    class="w-full pl-7"
                                                />
                                            </div>
                                            @error('quickTip3Dollars')
                                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-ink-muted dark:text-ink-soft">
                                        These appear in order on the audience request page.
                                    </p>
                                </div>

                                <div>
                                    <label class="mb-3 block text-sm font-medium text-ink dark:text-ink-inverse">
                                        Accept Requests
                                    </label>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="isAcceptingRequests"
                                            class="sr-only peer"
                                        >
                                        <div class="peer h-6 w-11 rounded-full bg-ink-border peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-300 dark:bg-ink-border-dark dark:peer-focus:ring-brand-900 peer-checked:bg-brand-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-ink-border after:bg-white after:transition-all after:content-[''] dark:after:border-ink-border-dark"></div>
                                        <span class="ml-3 text-sm text-ink dark:text-ink-inverse">
                                            {{ $isAcceptingRequests ? 'Active - accepting requests' : 'Inactive - not accepting requests' }}
                                        </span>
                                    </label>
                                </div>

                                <div>
                                    <label class="mb-3 block text-sm font-medium text-ink dark:text-ink-inverse">
                                        Accept Tips
                                    </label>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="isAcceptingTips"
                                            class="sr-only peer"
                                        >
                                        <div class="peer h-6 w-11 rounded-full bg-ink-border peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-300 dark:bg-ink-border-dark dark:peer-focus:ring-brand-900 peer-checked:bg-brand-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-ink-border after:bg-white after:transition-all after:content-[''] dark:after:border-ink-border-dark"></div>
                                        <span class="ml-3 text-sm text-ink dark:text-ink-inverse">
                                            {{ $isAcceptingTips ? 'Active - tips and tip jar are enabled' : 'Inactive - audience requests stay free' }}
                                        </span>
                                    </label>
                                </div>

                                <div>
                                    <label class="mb-3 block text-sm font-medium text-ink dark:text-ink-inverse">
                                        Accept Original Requests
                                    </label>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="isAcceptingOriginalRequests"
                                            class="sr-only peer"
                                        >
                                        <div class="peer h-6 w-11 rounded-full bg-ink-border peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-300 dark:bg-ink-border-dark dark:peer-focus:ring-brand-900 peer-checked:bg-brand-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-ink-border after:bg-white after:transition-all after:content-[''] dark:after:border-ink-border-dark"></div>
                                        <span class="ml-3 text-sm text-ink dark:text-ink-inverse">
                                            {{ $isAcceptingOriginalRequests ? 'Active - fans can request originals' : 'Inactive - originals are disabled' }}
                                        </span>
                                    </label>
                                </div>

                                <div x-data="{ fileName: 'No file chosen' }" class="space-y-3">
                                    <label for="edit-performerProfileImage" class="mb-1 block text-sm font-medium text-ink dark:text-ink-inverse">
                                        Performer Profile Image
                                    </label>
                                    <div class="flex items-center gap-3">
                                        <button
                                            type="button"
                                            x-on:click="$refs.performerProfileImageInput.click()"
                                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-ink-border bg-surface text-ink shadow-sm transition hover:border-brand-300 hover:text-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-ink-border-dark dark:bg-surface-elevated dark:text-ink-inverse dark:hover:border-brand-300 dark:hover:text-brand-100 dark:focus:ring-offset-canvas-dark"
                                            aria-label="Choose performer profile image"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15V4m0 0-4 4m4-4 4 4m5 8v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1"/>
                                            </svg>
                                        </button>
                                        <span
                                            class="min-w-0 text-sm text-ink-muted dark:text-ink-soft"
                                            wire:loading.remove
                                            wire:target="performerProfileImage"
                                            x-text="fileName"
                                        ></span>
                                        <span
                                            class="text-sm text-brand-600 dark:text-brand-300"
                                            wire:loading
                                            wire:target="performerProfileImage"
                                        >
                                            Uploading image...
                                        </span>
                                    </div>
                                    <input
                                        type="file"
                                        id="edit-performerProfileImage"
                                        x-ref="performerProfileImageInput"
                                        x-on:change="fileName = $event.target.files[0]?.name ?? 'No file chosen'"
                                        wire:model="performerProfileImage"
                                        accept="image/png,image/jpeg,image/webp"
                                        class="sr-only"
                                    >
                                    @error('performerProfileImage')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror

                                    @if ($performerProfileImage)
                                        <img
                                            src="{{ $performerProfileImage->temporaryUrl() }}"
                                            alt="Profile preview"
                                            class="mt-3 h-16 w-16 rounded-full object-cover ring-2 ring-brand-500/30 dark:ring-brand-300/40"
                                        >
                                    @elseif ($existingPerformerProfileImageUrl)
                                        <img
                                            src="{{ $existingPerformerProfileImageUrl }}"
                                            alt="Current profile image"
                                            class="mt-3 h-16 w-16 rounded-full object-cover ring-2 ring-brand-500/30 dark:ring-brand-300/40"
                                        >
                                    @endif

                                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-ink dark:text-ink-inverse">
                                        <input
                                            type="checkbox"
                                            wire:model="removePerformerProfileImage"
                                            class="rounded border-ink-border text-brand-600 focus:ring-brand-500 dark:border-ink-border-dark dark:bg-canvas-dark"
                                        >
                                        Remove current image
                                    </label>
                                </div>

                                <div class="md:col-span-2">
                                    <label for="edit-slug" class="mb-1 block text-sm font-medium text-ink dark:text-ink-inverse">
                                        URL Slug
                                    </label>
                                    <div class="flex overflow-hidden">
                                        <span class="inline-flex max-w-[50%] shrink-0 items-center rounded-l-lg border border-r-0 border-ink-border bg-surface-muted px-3 text-sm text-ink-muted truncate whitespace-nowrap dark:border-ink-border-dark dark:bg-surface-elevated dark:text-ink-soft">
                                            {{ url('/project') }}/
                                        </span>
                                        <x-text-input
                                            type="text"
                                            id="edit-slug"
                                            wire:model.live.debounce.300ms="slug"
                                            class="min-w-0 flex-1 rounded-none rounded-r-lg"
                                            placeholder="my-band-name"
                                        />
                                    </div>
                                    @error('slug')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-ink-muted dark:text-ink-soft">
                                        Only lowercase letters, numbers, and hyphens allowed.
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label for="edit-performerInfoUrl" class="mb-1 block text-sm font-medium text-ink dark:text-ink-inverse">
                                        Performer Info Link
                                    </label>
                                    <x-text-input
                                        type="url"
                                        id="edit-performerInfoUrl"
                                        wire:model.live.debounce.300ms="performerInfoUrl"
                                        class="w-full"
                                        placeholder="https://example.com/about"
                                    />
                                    @error('performerInfoUrl')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-3 block text-sm font-medium text-ink dark:text-ink-inverse">
                                        Suggested Setlist Range
                                    </label>
                                    <p class="mb-3 text-xs text-ink-muted dark:text-ink-soft">
                                        Audience members can suggest setlists with this many songs.
                                    </p>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="edit-minSuggestedSetlistSongs" class="mb-1 block text-xs font-medium uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                                                Minimum Songs
                                            </label>
                                            <x-text-input
                                                type="number"
                                                id="edit-minSuggestedSetlistSongs"
                                                wire:model="minSuggestedSetlistSongs"
                                                step="1"
                                                min="1"
                                                max="100"
                                                class="w-full"
                                            />
                                            @error('minSuggestedSetlistSongs')
                                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="edit-maxSuggestedSetlistSongs" class="mb-1 block text-xs font-medium uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                                                Maximum Songs
                                            </label>
                                            <x-text-input
                                                type="number"
                                                id="edit-maxSuggestedSetlistSongs"
                                                wire:model="maxSuggestedSetlistSongs"
                                                step="1"
                                                min="1"
                                                max="100"
                                                class="w-full"
                                            />
                                            @error('maxSuggestedSetlistSongs')
                                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Public Song List --}}
                        <div class="border-t border-ink-border px-6 py-4 dark:border-ink-border-dark">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-ink dark:text-ink-dark">Public Song List</h4>
                                    <p class="mt-1 text-sm text-ink-muted dark:text-ink-muted-dark">
                                        @if ($publicRepertoireSetId !== null)
                                            Showing: {{ $publicRepertoireSetLabel }}
                                        @else
                                            Showing full repertoire
                                        @endif
                                    </p>
                                </div>
                                @if ($publicRepertoireSetId !== null)
                                    <x-secondary-button type="button" wire:click="resetPublicSongList" class="text-sm">
                                        Show full repertoire
                                    </x-secondary-button>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-ink-border bg-surface-muted px-6 py-4 dark:border-ink-border-dark dark:bg-surface-elevated">
                            <x-secondary-button type="button" wire:click="cancel">
                                Cancel
                            </x-secondary-button>
                            <x-primary-button type="submit" class="font-medium disabled:cursor-not-allowed disabled:opacity-50" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="update">Save Changes</span>
                                <span wire:loading wire:target="update" class="inline-flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Saving...
                                </span>
                            </x-primary-button>
                        </div>
                    </form>
                </x-ui.panel>
            </div>
        </div>
    @endif
</div>
