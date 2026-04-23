<?php

use App\Models\TestChecklistItem;
use App\Models\TestChecklistProgress;
use App\Support\TestChecklist\Checklist;
use App\Support\TestChecklist\ChecklistDefinition;
use App\Support\TestChecklist\ChecklistItem;
use App\Support\TestChecklist\ChecklistSection;
use App\Support\TestChecklist\ChecklistSubsection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Map of checked item keys to `true`. Using a map keeps toggle O(1) and
     * avoids the JSON array index wire payloads that lists produce.
     *
     * @var array<string, bool>
     */
    public array $checkedKeys = [];

    /**
     * Per-subsection input state, keyed by "{sectionSlug}--{subsectionSlug}".
     * Each subsection footer has its own add-item form so admins can append
     * items directly under the subheading they belong to.
     *
     * @var array<string, string>
     */
    public array $newItemTexts = [];

    /**
     * The id of the item currently being edited inline, or null if no edit is
     * in progress. Only one item can be edited at a time so the UX stays simple.
     */
    public ?int $editingItemId = null;

    public string $editingText = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $this->seedIfEmpty();

        $progress = TestChecklistProgress::query()
            ->where('user_id', Auth::id())
            ->first();

        $stored = is_array($progress?->checked_keys) ? $progress->checked_keys : [];
        $valid = $this->checklist()->filterValidKeys($stored);

        $this->checkedKeys = array_fill_keys($valid, true);
    }

    /**
     * On the very first render for a user, copy the canonical
     * ChecklistDefinition rows into the per-user table. From then on the
     * admin owns every row and can edit/delete/add freely.
     */
    private function seedIfEmpty(): void
    {
        $userId = Auth::id();

        if (TestChecklistItem::query()->where('user_id', $userId)->exists()) {
            return;
        }

        $base = app(ChecklistDefinition::class)->checklist();
        $now = now();
        $rows = [];

        $sectionOrder = 0;
        foreach ($base->sections as $section) {
            $subsectionOrder = 0;
            foreach ($section->subsections as $subsection) {
                $position = 0;
                foreach ($subsection->items as $item) {
                    $rows[] = [
                        'user_id' => $userId,
                        'section_title' => $section->title,
                        'section_order' => $sectionOrder,
                        'subsection_title' => $subsection->title,
                        'subsection_order' => $subsectionOrder,
                        'position' => $position,
                        'text' => $item->text,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $position++;
                }
                $subsectionOrder++;
            }
            $sectionOrder++;
        }

        if ($rows !== []) {
            TestChecklistItem::query()->insert($rows);
        }
    }

    #[Computed]
    public function checklist(): Checklist
    {
        $items = TestChecklistItem::query()
            ->where('user_id', Auth::id())
            ->orderBy('section_order')
            ->orderBy('subsection_order')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return new Checklist([]);
        }

        // Single-pass group: items arrive sorted by (section_order,
        // subsection_order, position), so we just flush whenever the
        // section or subsection title changes. O(n) and zero PHP sorts.
        $sections = [];
        $sectionSubs = [];
        $subItems = [];
        $currentSectionTitle = $items->first()->section_title;
        $currentSubsectionTitle = $items->first()->subsection_title;

        foreach ($items as $item) {
            if ($item->section_title !== $currentSectionTitle) {
                $sectionSubs[] = new ChecklistSubsection($currentSubsectionTitle, $subItems);
                $sections[] = new ChecklistSection($currentSectionTitle, $sectionSubs);
                $currentSectionTitle = $item->section_title;
                $currentSubsectionTitle = $item->subsection_title;
                $sectionSubs = [];
                $subItems = [];
            } elseif ($item->subsection_title !== $currentSubsectionTitle) {
                $sectionSubs[] = new ChecklistSubsection($currentSubsectionTitle, $subItems);
                $currentSubsectionTitle = $item->subsection_title;
                $subItems = [];
            }

            $subItems[] = new ChecklistItem(
                key: $item->checklistKey(),
                text: $item->text,
            );
        }

        $sectionSubs[] = new ChecklistSubsection($currentSubsectionTitle, $subItems);
        $sections[] = new ChecklistSection($currentSectionTitle, $sectionSubs);

        return new Checklist($sections);
    }

    /**
     * Reverse map from a checklist key (`item-{id}`) back to its database id.
     * The view uses this to render edit/delete buttons against any item.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function itemIdsByKey(): array
    {
        return TestChecklistItem::query()
            ->where('user_id', Auth::id())
            ->pluck('id')
            ->mapWithKeys(fn (int $id): array => ['item-'.$id => $id])
            ->all();
    }

    #[Computed]
    public function totalItems(): int
    {
        return $this->checklist()->totalItems();
    }

    #[Computed]
    public function checkedCount(): int
    {
        return count($this->checkedKeys);
    }

    #[Computed]
    public function progressPercent(): int
    {
        $total = $this->totalItems();

        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->checkedCount() / $total) * 100);
    }

    public function toggle(string $key): void
    {
        if (! $this->checklist()->hasKey($key)) {
            return;
        }

        if (isset($this->checkedKeys[$key])) {
            unset($this->checkedKeys[$key]);
        } else {
            $this->checkedKeys[$key] = true;
        }

        $this->persist();
    }

    public function addItem(string $sectionSlug, string $subsectionSlug): void
    {
        $anchor = $sectionSlug.'--'.$subsectionSlug;
        $field = "newItemTexts.{$anchor}";
        $this->resetErrorBag($field);

        $reference = $this->findReferenceItem($sectionSlug, $subsectionSlug);
        if ($reference === null) {
            return;
        }

        $text = trim((string) ($this->newItemTexts[$anchor] ?? ''));

        // Pass nested data so Laravel's dot-path lookup matches the field key.
        $validator = Validator::make(
            ['newItemTexts' => [$anchor => $text]],
            [$field => ['required', 'string', 'min:2', 'max:500']],
            [
                $field.'.required' => 'Enter an item to add.',
                $field.'.min' => 'Item must be at least 2 characters.',
                $field.'.max' => 'Item must be 500 characters or fewer.',
            ],
        );

        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());

            return;
        }

        $nextPosition = ((int) TestChecklistItem::query()
            ->where('user_id', Auth::id())
            ->where('section_title', $reference->section_title)
            ->where('subsection_title', $reference->subsection_title)
            ->max('position')) + 1;

        TestChecklistItem::query()->create([
            'user_id' => Auth::id(),
            'section_title' => $reference->section_title,
            'section_order' => $reference->section_order,
            'subsection_title' => $reference->subsection_title,
            'subsection_order' => $reference->subsection_order,
            'position' => $nextPosition,
            'text' => $text,
        ]);

        unset($this->newItemTexts[$anchor]);
        $this->forgetCachedComputed();
    }

    public function removeItem(int $id): void
    {
        $item = TestChecklistItem::query()
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if ($item === null) {
            return;
        }

        $key = $item->checklistKey();
        $item->delete();

        if (isset($this->checkedKeys[$key])) {
            unset($this->checkedKeys[$key]);
            $this->persist();
        }

        if ($this->editingItemId === $id) {
            $this->editingItemId = null;
            $this->editingText = '';
        }

        $this->forgetCachedComputed();
    }

    public function startEdit(int $id): void
    {
        $item = TestChecklistItem::query()
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if ($item === null) {
            return;
        }

        $this->editingItemId = $id;
        $this->editingText = $item->text;
        $this->resetErrorBag('editingText');
    }

    public function cancelEdit(): void
    {
        $this->editingItemId = null;
        $this->editingText = '';
        $this->resetErrorBag('editingText');
    }

    public function saveEdit(): void
    {
        if ($this->editingItemId === null) {
            return;
        }

        $this->resetErrorBag('editingText');

        $item = TestChecklistItem::query()
            ->where('user_id', Auth::id())
            ->where('id', $this->editingItemId)
            ->first();

        if ($item === null) {
            $this->cancelEdit();

            return;
        }

        $text = trim($this->editingText);

        $validator = Validator::make(
            ['editingText' => $text],
            ['editingText' => ['required', 'string', 'min:2', 'max:500']],
            [
                'editingText.required' => 'Item cannot be empty.',
                'editingText.min' => 'Item must be at least 2 characters.',
                'editingText.max' => 'Item must be 500 characters or fewer.',
            ],
        );

        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());

            return;
        }

        $item->update(['text' => $text]);

        $this->editingItemId = null;
        $this->editingText = '';
        $this->forgetCachedComputed();
    }

    /**
     * Unchecks every item. Pure by design: it does NOT touch rows, text,
     * order, or any in-flight edit/add state. Anything the admin added,
     * edited, deleted, or rearranged stays exactly as it was.
     */
    public function resetAll(): void
    {
        $this->checkedKeys = [];

        TestChecklistProgress::query()
            ->where('user_id', Auth::id())
            ->delete();
    }

    /**
     * Persists a new order for items within a single subsection. The client
     * hands back the full list of row ids in the order the admin dropped
     * them; we rewrite `position` in a single sweep scoped to those ids so
     * the list re-hydrates in the new order on the next render.
     *
     * Cross-subsection drags are rejected — every id in the payload must
     * already belong to the same (section_title, subsection_title) pair
     * that the slugs resolve to. This keeps the data model honest and
     * prevents a malicious client from moving rows into sections they
     * weren't dragged into.
     *
     * @param  list<int|string>  $orderedIds
     */
    public function reorderItems(string $sectionSlug, string $subsectionSlug, array $orderedIds): void
    {
        if ($orderedIds === []) {
            return;
        }

        $reference = $this->findReferenceItem($sectionSlug, $subsectionSlug);
        if ($reference === null) {
            return;
        }

        $ids = array_values(array_filter(array_map('intval', $orderedIds), fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return;
        }

        $rows = TestChecklistItem::query()
            ->where('user_id', Auth::id())
            ->where('section_title', $reference->section_title)
            ->where('subsection_title', $reference->subsection_title)
            ->whereIn('id', $ids)
            ->get();

        // Require every id in the payload to exist inside this subsection
        // for this user. Silently ignoring unknown ids lets a client nudge
        // items out of sync — safer to abort the whole reorder.
        if ($rows->count() !== count($ids)) {
            return;
        }

        $rowsById = $rows->keyBy('id');
        $position = 0;
        foreach ($ids as $id) {
            $row = $rowsById->get($id);
            if ($row === null) {
                return;
            }

            if ($row->position !== $position) {
                $row->update(['position' => $position]);
            }

            $position++;
        }

        $this->forgetCachedComputed();
    }

    private function persist(): void
    {
        TestChecklistProgress::query()->updateOrCreate(
            ['user_id' => Auth::id()],
            ['checked_keys' => array_keys($this->checkedKeys)],
        );
    }

    /**
     * Look up a representative row for a (section, subsection) pair so newly
     * added items can inherit the parent's section_title, section_order,
     * subsection_title, and subsection_order without the caller having to
     * supply them. Returns null if the slugged pair has no rows — i.e. the
     * subsection is empty or never existed.
     */
    private function findReferenceItem(string $sectionSlug, string $subsectionSlug): ?TestChecklistItem
    {
        foreach ($this->checklist->sections as $section) {
            if ($section->slug() !== $sectionSlug) {
                continue;
            }

            foreach ($section->subsections as $subsection) {
                if ($subsection->slug() !== $subsectionSlug) {
                    continue;
                }

                return TestChecklistItem::query()
                    ->where('user_id', Auth::id())
                    ->where('section_title', $section->title)
                    ->where('subsection_title', $subsection->title)
                    ->first();
            }
        }

        return null;
    }

    private function forgetCachedComputed(): void
    {
        unset(
            $this->checklist,
            $this->itemIdsByKey,
            $this->totalItems,
            $this->checkedCount,
            $this->progressPercent,
        );
    }
}; ?>

<div class="py-12" data-test="admin-test-checklist-page"
     x-data="{ confirmDelete: { open: false, itemId: null }, confirmReset: false }">
    <aside
        class="mx-auto mb-6 max-w-7xl px-4 sm:px-6 lg:fixed lg:inset-y-20 lg:left-4 lg:bottom-8 lg:m-0 lg:w-64 lg:px-0 lg:z-20"
        data-test="checklist-sidebar"
    >
        <nav
            class="rounded-2xl border border-ink-border/80 bg-surface/95 p-4 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto"
            aria-label="Checklist sections"
        >
            <p class="px-2 pb-3 text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">
                Jump to
            </p>
            <ul class="space-y-1">
                @foreach ($this->checklist->sections as $section)
                    <li>
                        <a
                            href="#section-{{ $section->slug() }}"
                            class="flex items-center justify-between rounded-md px-2 py-1.5 text-sm font-medium text-ink hover:bg-surface-muted dark:text-ink-inverse dark:hover:bg-surface-elevated"
                            data-test="checklist-sidebar-section-link"
                        >
                            <span class="truncate">{{ $section->title }}</span>
                            <span class="ml-2 shrink-0 text-xs font-normal text-ink-muted dark:text-ink-soft">{{ $section->itemCount() }}</span>
                        </a>
                        @if (! empty($section->subsections))
                            <ul class="mt-1 space-y-0.5 border-l border-ink-border pl-3 dark:border-ink-border-dark">
                                @foreach ($section->subsections as $subsection)
                                    <li>
                                        <a
                                            href="#subsection-{{ $section->subsectionAnchorId($subsection) }}"
                                            class="block truncate rounded-md px-2 py-1 text-xs text-ink-muted hover:bg-surface-muted hover:text-ink dark:text-ink-soft dark:hover:bg-surface-elevated dark:hover:text-ink-inverse"
                                            data-test="checklist-sidebar-subsection-link"
                                        >
                                            {{ $subsection->title }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </aside>

    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:pl-[19rem] lg:pr-8">
        <div class="sticky top-0 z-10 rounded-2xl border border-ink-border/80 bg-surface/95 shadow-sm backdrop-blur-md dark:border-ink-border-dark/80 dark:bg-surface-inverse/90">
            <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">
                        SongTipper Manual Test Checklist
                    </h3>
                    <p class="text-sm text-ink-muted dark:text-ink-soft">
                        Progress saves automatically to your account. Use this before tagging a release.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">Progress</p>
                        <p class="text-2xl font-bold text-ink dark:text-ink-inverse" data-test="checklist-progress-counter">
                            {{ $this->checkedCount }} / {{ $this->totalItems }}
                            <span class="ml-1 text-sm font-medium text-ink-muted dark:text-ink-soft">({{ $this->progressPercent }}%)</span>
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="confirmReset = true"
                        class="inline-flex items-center rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-400 dark:border-red-700/50 dark:bg-red-950/30 dark:text-red-200 dark:hover:bg-red-900/40"
                        data-test="checklist-reset-all"
                    >
                        Reset all
                    </button>
                </div>
            </div>

            <div class="px-6 pb-5">
                <div class="h-2 w-full overflow-hidden rounded-full bg-surface-muted dark:bg-surface-elevated">
                    <div
                        class="h-full bg-brand-500 transition-all duration-300"
                        style="width: {{ $this->progressPercent }}%"
                        data-test="checklist-progress-bar"
                    ></div>
                </div>
            </div>
        </div>

        @foreach ($this->checklist->sections as $section)
            <section
                wire:key="section-{{ $section->slug() }}"
                id="section-{{ $section->slug() }}"
                class="scroll-mt-32 rounded-2xl border border-ink-border/80 bg-surface shadow-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90"
                data-test="checklist-section"
            >
                <header class="border-b border-ink-border px-6 py-4 dark:border-ink-border-dark">
                    <h4 class="text-base font-semibold text-ink dark:text-ink-inverse">
                        {{ $section->title }}
                        <span class="ml-2 text-sm font-normal text-ink-muted dark:text-ink-soft">({{ $section->itemCount() }})</span>
                    </h4>
                </header>

                <div class="divide-y divide-ink-border dark:divide-ink-border-dark">
                    @foreach ($section->subsections as $subsection)
                        @php($anchor = $section->subsectionAnchorId($subsection))
                        <details
                            wire:key="subsection-{{ $anchor }}"
                            id="subsection-{{ $anchor }}"
                            class="group scroll-mt-32 px-6 py-4"
                            open
                        >
                            <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-semibold text-ink dark:text-ink-inverse">
                                <span>{{ $subsection->title }}</span>
                                <span class="text-xs font-normal text-ink-muted dark:text-ink-soft">{{ $subsection->itemCount() }} item{{ $subsection->itemCount() === 1 ? '' : 's' }}</span>
                            </summary>

                            <ul
                                class="mt-3 space-y-2"
                                data-test="checklist-items-list-{{ $anchor }}"
                                x-data="checklistSortable(@js($section->slug()), @js($subsection->slug()))"
                            >
                                @foreach ($subsection->items as $item)
                                    @php($itemId = $this->itemIdsByKey[$item->key] ?? null)
                                    {{-- wire:key forces Livewire to remove+insert (not morph)
                                         so checkbox DOM properties stay in sync with server state.
                                         data-reorder-id is how SortableJS identifies rows on drop. --}}
                                    <li
                                        wire:key="checklist-row-{{ $item->key }}"
                                        @if ($itemId !== null) data-reorder-id="{{ $itemId }}" @endif
                                        class="flex items-start gap-2 rounded-md {{ $itemId !== null ? 'cursor-grab active:cursor-grabbing' : '' }}"
                                        @if ($itemId !== null) data-test="checklist-item-row-{{ $itemId }}" @endif
                                    >
                                        @if ($itemId !== null && $editingItemId === $itemId)
                                            <form
                                                wire:submit="saveEdit"
                                                class="flex w-full flex-col gap-2 sm:flex-row sm:items-start"
                                                data-test="checklist-edit-form-{{ $itemId }}"
                                            >
                                                <div class="flex-1">
                                                    <x-text-input
                                                        type="text"
                                                        wire:model="editingText"
                                                        class="w-full"
                                                        maxlength="500"
                                                        autofocus
                                                        data-test="checklist-edit-input-{{ $itemId }}"
                                                    />
                                                    @error('editingText')
                                                        <p class="mt-1 text-sm text-red-600 dark:text-red-300" data-test="checklist-edit-error-{{ $itemId }}">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                                <div class="flex gap-2">
                                                    <x-primary-button
                                                        type="submit"
                                                        class="shrink-0 px-3 py-2"
                                                        data-test="checklist-edit-save-{{ $itemId }}"
                                                    >
                                                        Save
                                                    </x-primary-button>
                                                    <button
                                                        type="button"
                                                        wire:click="cancelEdit"
                                                        class="inline-flex items-center rounded-md border border-ink-border bg-surface px-3 py-2 text-sm font-medium text-ink-muted transition hover:bg-surface-muted hover:text-ink dark:border-ink-border-dark dark:bg-surface-inverse dark:text-ink-soft dark:hover:bg-surface-elevated dark:hover:text-ink-inverse"
                                                        data-test="checklist-edit-cancel-{{ $itemId }}"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        @else
                                            <label class="flex flex-1 cursor-pointer items-start gap-3 rounded-md px-2 py-1.5 transition hover:bg-surface-muted dark:hover:bg-surface-elevated">
                                                {{-- autocomplete="off" tells the browser not to
                                                     restore the previous DOM .checked property on
                                                     back/forward/bfcache navigation, which is what
                                                     was making the visible tick marks go stale
                                                     after logout → login even though the server
                                                     state was correct. --}}
                                                <input
                                                    type="checkbox"
                                                    autocomplete="off"
                                                    class="mt-0.5 h-4 w-4 rounded border-ink-border text-brand-500 focus:ring-brand-500 dark:border-ink-border-dark"
                                                    wire:click="toggle('{{ $item->key }}')"
                                                    @checked(isset($checkedKeys[$item->key]))
                                                    data-test="checklist-item-{{ $item->key }}"
                                                />
                                                <span class="flex-1 text-sm {{ isset($checkedKeys[$item->key]) ? 'text-ink-muted line-through dark:text-ink-soft' : 'text-ink dark:text-ink-inverse' }}">
                                                    {{ $item->text }}
                                                </span>
                                            </label>
                                            @if ($itemId !== null)
                                                <button
                                                    type="button"
                                                    wire:click="startEdit({{ $itemId }})"
                                                    class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-ink-border text-ink-muted transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-400 dark:border-ink-border-dark dark:text-ink-soft dark:hover:border-brand-700/50 dark:hover:bg-brand-950/30 dark:hover:text-brand-300"
                                                    aria-label="Edit item"
                                                    data-test="checklist-edit-{{ $itemId }}"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.586 3.586a2 2 0 112.828 2.828l-9 9a2 2 0 01-.878.506l-3.121.78.78-3.121a2 2 0 01.506-.878l9-9z" />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="confirmDelete.itemId = {{ $itemId }}; confirmDelete.open = true"
                                                    class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-ink-border text-ink-muted transition hover:border-red-300 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 dark:border-ink-border-dark dark:text-ink-soft dark:hover:border-red-700/50 dark:hover:bg-red-950/30 dark:hover:text-red-300"
                                                    aria-label="Remove item"
                                                    data-test="checklist-remove-{{ $itemId }}"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l8 8M14 6l-8 8" />
                                                    </svg>
                                                </button>
                                            @endif
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-4 border-t border-dashed border-ink-border/70 pt-3 dark:border-ink-border-dark/70">
                                <form
                                    wire:submit="addItem('{{ $section->slug() }}', '{{ $subsection->slug() }}')"
                                    class="space-y-2"
                                    data-test="checklist-add-item-form-{{ $anchor }}"
                                >
                                    <label for="add-item-{{ $anchor }}" class="block text-xs font-semibold uppercase tracking-[0.18em] text-ink-muted dark:text-ink-soft">
                                        Add item to {{ $subsection->title }}
                                    </label>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                                        <div class="flex-1">
                                            <x-text-input
                                                id="add-item-{{ $anchor }}"
                                                type="text"
                                                wire:model="newItemTexts.{{ $anchor }}"
                                                class="w-full"
                                                placeholder="e.g. Verify the empty state on small screens"
                                                data-test="new-item-input-{{ $anchor }}"
                                                maxlength="500"
                                            />
                                            @error('newItemTexts.'.$anchor)
                                                <p class="mt-1 text-sm text-red-600 dark:text-red-300" data-test="new-item-error-{{ $anchor }}">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <x-primary-button
                                            type="submit"
                                            class="shrink-0 px-4 py-2 disabled:cursor-not-allowed disabled:opacity-60"
                                            wire:loading.attr="disabled"
                                            wire:target="addItem('{{ $section->slug() }}', '{{ $subsection->slug() }}')"
                                            data-test="add-item-button-{{ $anchor }}"
                                        >
                                            <span wire:loading.remove wire:target="addItem('{{ $section->slug() }}', '{{ $subsection->slug() }}')">Add item</span>
                                            <span wire:loading wire:target="addItem('{{ $section->slug() }}', '{{ $subsection->slug() }}')">Adding...</span>
                                        </x-primary-button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    {{-- Delete item confirmation modal --}}
    <div
        x-show="confirmDelete.open"
        style="display:none"
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="confirmDelete.open = false"
    >
        <div
            class="absolute inset-0 bg-ink/45 dark:bg-black/70"
            @click="confirmDelete.open = false"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>
        <div
            class="relative w-full max-w-sm rounded-2xl border border-ink-border/80 bg-surface/95 p-6 shadow-xl backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <h3 class="text-base font-semibold text-ink dark:text-ink-inverse">Remove item?</h3>
            <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">This cannot be undone.</p>
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    @click="confirmDelete.open = false"
                    class="inline-flex items-center rounded-lg border border-ink-border bg-surface px-4 py-2 text-sm font-medium text-ink-muted transition hover:bg-surface-muted hover:text-ink focus:outline-none focus:ring-2 focus:ring-ink-border dark:border-ink-border-dark dark:bg-surface-inverse dark:text-ink-soft dark:hover:bg-surface-elevated dark:hover:text-ink-inverse"
                    data-test="confirm-delete-cancel"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="$wire.removeItem(confirmDelete.itemId); confirmDelete.open = false"
                    class="inline-flex items-center rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-400 dark:border-red-700/50 dark:bg-red-950/30 dark:text-red-200 dark:hover:bg-red-900/40"
                    data-test="confirm-delete-confirm"
                >
                    Remove
                </button>
            </div>
        </div>
    </div>

    {{-- Reset all confirmation modal --}}
    <div
        x-show="confirmReset"
        style="display:none"
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="confirmReset = false"
    >
        <div
            class="absolute inset-0 bg-ink/45 dark:bg-black/70"
            @click="confirmReset = false"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>
        <div
            class="relative w-full max-w-sm rounded-2xl border border-ink-border/80 bg-surface/95 p-6 shadow-xl backdrop-blur-sm dark:border-ink-border-dark/80 dark:bg-surface-inverse/90"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <h3 class="text-base font-semibold text-ink dark:text-ink-inverse">Reset all progress?</h3>
            <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">Uncheck every checklist item? Your items, text edits, and order stay exactly as they are.</p>
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    @click="confirmReset = false"
                    class="inline-flex items-center rounded-lg border border-ink-border bg-surface px-4 py-2 text-sm font-medium text-ink-muted transition hover:bg-surface-muted hover:text-ink focus:outline-none focus:ring-2 focus:ring-ink-border dark:border-ink-border-dark dark:bg-surface-inverse dark:text-ink-soft dark:hover:bg-surface-elevated dark:hover:text-ink-inverse"
                    data-test="confirm-reset-cancel"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="$wire.resetAll(); confirmReset = false"
                    class="inline-flex items-center rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-400 dark:border-red-700/50 dark:bg-red-950/30 dark:text-red-200 dark:hover:bg-red-900/40"
                    data-test="confirm-reset-confirm"
                >
                    Reset all
                </button>
            </div>
        </div>
    </div>
</div>
