<?php

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public function placeholder(): string
    {
        return <<<'HTML'
        <section id="search" class="relative z-[1] bg-canvas-light pt-[calc(5rem+4vw)] pb-20 dark:bg-canvas-dark">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-12 text-center">
                    <h2 class="mb-4 font-display text-3xl font-bold text-ink dark:text-ink-inverse sm:text-4xl">
                        Find a Performer
                    </h2>
                    <p class="text-xl text-ink-muted dark:text-ink-soft">
                        Search for a band, performer, or DJ
                    </p>
                </div>

                <div class="overflow-hidden rounded-3xl border border-ink-border bg-surface shadow-lg dark:border-ink-border-dark dark:bg-surface-inverse">
                    <div class="p-8">
                        <div class="h-14 w-full rounded-xl bg-surface-muted dark:bg-canvas-dark"></div>
                        <div class="mt-8">
                            <div class="mb-4 h-5 w-40 rounded bg-ink-subtle/20 dark:bg-ink-soft/20"></div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="h-20 rounded-xl bg-surface-muted dark:bg-canvas-dark"></div>
                                <div class="h-20 rounded-xl bg-surface-muted dark:bg-canvas-dark"></div>
                                <div class="h-20 rounded-xl bg-surface-muted dark:bg-canvas-dark"></div>
                                <div class="h-20 rounded-xl bg-surface-muted dark:bg-canvas-dark"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        HTML;
    }

    #[Computed]
    public function projects(): Collection
    {
        if (strlen($this->search) < 2) {
            return collect();
        }

        return Project::query()
            ->select(['id', 'name', 'slug', 'performer_profile_image_path'])
            ->where('is_accepting_requests', true)
            ->where(function (Builder $query): void {
                $search = "%{$this->search}%";

                $query
                    ->where('name', 'like', $search)
                    ->orWhere('slug', 'like', $search)
                    ->orWhereHas('owner', function (Builder $ownerQuery) use ($search): void {
                        $ownerQuery->where('name', 'like', $search);
                    });
            })
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function featuredProjects(): Collection
    {
        $featuredProjectIds = Cache::remember(
            'home:featured-project-ids:v1',
            now()->addMinutes(15),
            static fn (): array => Project::query()
                ->where('is_accepting_requests', true)
                ->inRandomOrder()
                ->limit(6)
                ->pluck('id')
                ->all(),
        );

        if ($featuredProjectIds === []) {
            return collect();
        }

        $projectsById = Project::query()
            ->select(['id', 'name', 'slug', 'performer_profile_image_path'])
            ->whereKey($featuredProjectIds)
            ->get()
            ->keyBy('id');

        return collect($featuredProjectIds)
            ->map(static fn (int $projectId) => $projectsById->get($projectId))
            ->filter()
            ->values();
    }
};
?>

<section id="search" class="relative z-[1] bg-canvas-light pt-[calc(5rem+4vw)] pb-20 dark:bg-canvas-dark">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="mb-4 font-display text-3xl font-bold text-ink dark:text-ink-inverse sm:text-4xl">
                Find a Performer
            </h2>
            <p class="text-xl text-ink-muted dark:text-ink-soft">
                Search for a band, performer, or DJ
            </p>
        </div>

        <x-ui.card class="overflow-hidden shadow-lg">
            <div class="p-8">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-ink-subtle dark:text-ink-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <x-text-input type="text" wire:model.live.debounce.300ms="search" placeholder="Search for a band, venue, or event..." class="w-full border-2 py-4 pl-12 pr-4 text-lg transition-colors" />
                </div>

                @if (strlen($search) >= 2)
                    <div class="mt-6">
                        @if ($this->projects->isEmpty())
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-ink-subtle dark:text-ink-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="mt-4 text-ink-muted dark:text-ink-soft">No projects found matching "{{ $search }}"</p>
                                <p class="mt-1 text-sm text-ink-subtle dark:text-ink-soft">Try searching by name or venue</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach ($this->projects as $project)
                                    <a href="{{ route('project.page', $project->slug) }}" wire:key="project-{{ $project->id }}" class="group flex items-center rounded-xl border-2 border-ink-border p-4 transition-all hover:border-brand-300 hover:bg-accent dark:border-ink-border-dark dark:hover:border-brand-300 dark:hover:bg-brand-900/20">
                                        @if ($project->performer_profile_image_url)
                                            <img src="{{ $project->performer_profile_image_url }}" alt="{{ $project->name }}" class="w-12 h-12 rounded-lg object-cover shrink-0 mr-4" loading="lazy">
                                        @else
                                            <div class="mr-4 flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-accent-500">
                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                                </svg>
                                            </div>
                                        @endif
                                        <div class="flex-1 min-w-0">
                                            <h4 class="truncate text-sm font-semibold text-ink transition-colors duration-150 ease-in-out group-hover:text-brand-600 dark:text-ink-inverse dark:group-hover:text-brand-300">
                                                {{ $project->name }}
                                            </h4>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    @if ($this->featuredProjects->isNotEmpty())
                        <div class="mt-8">
                            <h3 class="mb-4 text-lg font-semibold text-ink dark:text-ink-inverse">Featured Projects</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach ($this->featuredProjects as $project)
                                    <a href="{{ route('project.page', $project->slug) }}" wire:key="featured-{{ $project->id }}" class="group flex items-center rounded-xl border-2 border-ink-border p-4 transition-all hover:border-brand-300 hover:bg-accent dark:border-ink-border-dark dark:hover:border-brand-300 dark:hover:bg-brand-900/20">
                                        @if ($project->performer_profile_image_url)
                                            <img src="{{ $project->performer_profile_image_url }}" alt="{{ $project->name }}" class="w-12 h-12 rounded-lg object-cover shrink-0 mr-4" loading="lazy">
                                        @else
                                            <div class="mr-4 flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-accent-500">
                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                                </svg>
                                            </div>
                                        @endif
                                        <div class="flex-1 min-w-0">
                                            <h4 class="truncate text-sm font-semibold text-ink transition-colors duration-150 ease-in-out group-hover:text-brand-600 dark:text-ink-inverse dark:group-hover:text-brand-300">
                                                {{ $project->name }}
                                            </h4>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mt-8 text-center py-8">
                            <p class="text-ink-muted dark:text-ink-soft">Start typing to search for projects</p>
                        </div>
                    @endif
                @endif
            </div>
        </x-ui.card>
    </div>
</section>
