<?php

use App\Models\Project;
use App\Support\TipAmount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:255|unique:projects,slug|regex:/^[a-z0-9-]+$/')]
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

    public bool $showForm = false;

    public bool $slugManuallyEdited = false;

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

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if (! $this->showForm) {
            $this->reset([
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
                'slugManuallyEdited',
            ]);
            $this->minTipDollars = 5.00;
            $this->quickTip1Dollars = 20.00;
            $this->quickTip2Dollars = 15.00;
            $this->quickTip3Dollars = 10.00;
            $this->isAcceptingRequests = true;
            $this->isAcceptingTips = true;
            $this->isAcceptingOriginalRequests = true;
            $this->resetValidation();
        }
    }

    public function create(): void
    {
        $this->validate();
        $quickTipAmounts = $this->validatedQuickTipAmounts();
        if ($quickTipAmounts === null) {
            return;
        }

        $project = Project::create([
            'owner_user_id' => Auth::id(),
            'name' => $this->name,
            'slug' => $this->slug,
            'performer_info_url' => $this->performerInfoUrl !== '' ? $this->performerInfoUrl : null,
            'min_tip_cents' => TipAmount::normalizeCents(
                (int) round($this->minTipDollars * 100)
            ),
            ...Project::quickTipAttributes($quickTipAmounts),
            'is_accepting_requests' => $this->isAcceptingRequests,
            'is_accepting_tips' => $this->isAcceptingTips,
            'is_accepting_original_requests' => $this->isAcceptingOriginalRequests,
        ]);

        if ($this->performerProfileImage !== null) {
            $project->update([
                'performer_profile_image_path' => $this->performerProfileImage->store(
                    "performers/{$project->id}",
                    'public'
                ),
            ]);
        }

        $this->reset([
            'name',
            'slug',
            'minTipDollars',
            'quickTip1Dollars',
            'quickTip2Dollars',
            'quickTip3Dollars',
            'performerInfoUrl',
            'performerProfileImage',
            'showForm',
            'slugManuallyEdited',
        ]);
        $this->minTipDollars = 5.00;
        $this->quickTip1Dollars = 20.00;
        $this->quickTip2Dollars = 15.00;
        $this->quickTip3Dollars = 10.00;
        $this->isAcceptingRequests = true;
        $this->isAcceptingTips = true;
        $this->isAcceptingOriginalRequests = true;
        $this->resetValidation();

        $this->dispatch('project-created', projectId: $project->id);
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
    @if (! $showForm)
        <x-primary-button wire:click="toggleForm" type="button" class="gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Create New Project
        </x-primary-button>
    @else
        <form wire:submit="create" class="space-y-6">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-medium text-ink dark:text-ink-inverse">Create New Project</h4>
                <button
                    type="button"
                    wire:click="toggleForm"
                    class="text-ink-subtle transition hover:text-ink dark:text-ink-soft dark:hover:text-ink-inverse"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                        Project Name
                    </label>
                    <x-text-input
                        type="text"
                        id="name"
                        wire:model.live.debounce.300ms="name"
                        class="w-full"
                        placeholder="My Band Name"
                    />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="slug" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                        URL Slug
                    </label>
                    <div class="flex">
                        <span class="inline-flex items-center rounded-l-lg border border-r-0 border-ink-border bg-surface-muted px-3 text-sm text-ink-muted dark:border-ink-border-dark dark:bg-surface-elevated dark:text-ink-soft">
                            {{ url('/project') }}/
                        </span>
                        <x-text-input
                            type="text"
                            id="slug"
                            wire:model.live.debounce.300ms="slug"
                            class="flex-1 rounded-none rounded-r-lg"
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

                <div>
                    <label for="minTipDollars" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                        Minimum Tip Amount
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-ink-muted dark:text-ink-soft">$</span>
                        </div>
                        <x-text-input
                            type="number"
                            id="minTipDollars"
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
                    <label class="mb-3 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                        Audience Quick Tip Buttons
                    </label>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label for="quickTip1Dollars" class="mb-1 block text-xs font-medium uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                                Top Button
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-ink-muted dark:text-ink-soft">$</span>
                                </div>
                                <x-text-input
                                    type="number"
                                    id="quickTip1Dollars"
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
                            <label for="quickTip2Dollars" class="mb-1 block text-xs font-medium uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                                Middle Button
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-ink-muted dark:text-ink-soft">$</span>
                                </div>
                                <x-text-input
                                    type="number"
                                    id="quickTip2Dollars"
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
                            <label for="quickTip3Dollars" class="mb-1 block text-xs font-medium uppercase tracking-wide text-ink-muted dark:text-ink-soft">
                                Bottom Button
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-ink-muted dark:text-ink-soft">$</span>
                                </div>
                                <x-text-input
                                    type="number"
                                    id="quickTip3Dollars"
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
                    <label class="mb-3 block text-sm font-medium text-ink-muted dark:text-ink-soft">
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
                    <label class="mb-3 block text-sm font-medium text-ink-muted dark:text-ink-soft">
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
                    <label class="mb-3 block text-sm font-medium text-ink-muted dark:text-ink-soft">
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

                <div>
                    <label for="performerInfoUrl" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                        Artist Info Link
                    </label>
                    <x-text-input
                        type="url"
                        id="performerInfoUrl"
                        wire:model.live.debounce.300ms="performerInfoUrl"
                        class="w-full"
                        placeholder="https://example.com/about"
                    />
                    @error('performerInfoUrl')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div x-data="{ fileName: 'No file chosen' }" class="space-y-3">
                    <label for="performerProfileImage" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                        Performer Profile Image
                    </label>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            x-on:click="$refs.performerProfileImageInput.click()"
                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-ink-border bg-surface text-ink-muted shadow-sm transition hover:border-brand-300 hover:text-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-ink-border-dark dark:bg-surface-inverse dark:text-ink-soft dark:hover:border-brand-300 dark:hover:text-brand-300 dark:focus:ring-offset-canvas-dark"
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
                        id="performerProfileImage"
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
                            class="mt-3 h-16 w-16 rounded-full object-cover ring-2 ring-brand-300/60"
                        >
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-4 border-t border-ink-border pt-4 dark:border-ink-border-dark">
                <x-primary-button type="submit" class="disabled:cursor-not-allowed disabled:opacity-50" wire:loading.attr="disabled">
                    <span wire:loading.remove>Create Project</span>
                    <span wire:loading class="inline-flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating...
                    </span>
                </x-primary-button>
                <button
                    type="button"
                    wire:click="toggleForm"
                    class="px-4 py-2 font-medium text-ink-muted transition hover:text-ink dark:text-ink-soft dark:hover:text-ink-inverse"
                >
                    Cancel
                </button>
            </div>
        </form>
    @endif
</div>
