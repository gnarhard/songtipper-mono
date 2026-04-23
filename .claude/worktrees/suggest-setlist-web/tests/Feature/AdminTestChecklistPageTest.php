<?php

declare(strict_types=1);

use App\Models\AdminDesignation;
use App\Models\TestChecklistItem;
use App\Models\TestChecklistProgress;
use App\Models\User;
use App\Support\TestChecklist\ChecklistDefinition;
use Livewire\Livewire;

function adminChecklistUser(): User
{
    $admin = billingReadyUser(['email' => 'checklist-admin@example.com']);
    AdminDesignation::factory()->create(['email' => $admin->email]);

    return $admin;
}

/**
 * The component seeds rows on first mount; spinning it up once is the
 * cheapest way to make a fully populated checklist available to a test.
 */
function seedChecklistFor(User $user): void
{
    Livewire::actingAs($user)->test('admin-test-checklist-page');
}

/**
 * Convenience: load the first row in a known section/subsection so a test
 * can drive edit/delete actions against an actual seeded id.
 */
function firstSeededItem(User $user): TestChecklistItem
{
    return TestChecklistItem::query()
        ->where('user_id', $user->id)
        ->orderBy('section_order')
        ->orderBy('subsection_order')
        ->orderBy('position')
        ->orderBy('id')
        ->firstOrFail();
}

it('guards the admin test checklist page behind admin middleware', function () {
    $this->actingAs(billingReadyUser())
        ->get('/admin/test-checklist')
        ->assertStatus(403);
});

it('renders the test checklist page for admins', function () {
    $this->actingAs(adminChecklistUser())
        ->get('/admin/test-checklist')
        ->assertOk()
        ->assertSee('SongTipper Manual Test Checklist');
});

it('seeds the canonical checklist on first mount for a new admin', function () {
    $admin = adminChecklistUser();

    expect(TestChecklistItem::query()->where('user_id', $admin->id)->count())->toBe(0);

    seedChecklistFor($admin);

    $expected = app(ChecklistDefinition::class)->checklist()->totalItems();
    expect(TestChecklistItem::query()->where('user_id', $admin->id)->count())->toBe($expected);
});

it('does not reseed when the user already has rows', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $countAfterFirstMount = TestChecklistItem::query()->where('user_id', $admin->id)->count();

    seedChecklistFor($admin);
    seedChecklistFor($admin);

    expect(TestChecklistItem::query()->where('user_id', $admin->id)->count())
        ->toBe($countAfterFirstMount);
});

it('shows sections, subsections, and items from the seeded checklist', function () {
    $admin = adminChecklistUser();
    $checklist = app(ChecklistDefinition::class)->checklist();
    $firstSection = $checklist->sections[0];
    $firstSubsection = $firstSection->subsections[0];
    $firstItem = $firstSubsection->items[0];

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->assertSee($firstSection->title)
        ->assertSee($firstSubsection->title)
        ->assertSee($firstItem->text);
});

it('starts with zero checked items and full total visible', function () {
    $admin = adminChecklistUser();
    $total = app(ChecklistDefinition::class)->checklist()->totalItems();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->assertSet('checkedKeys', [])
        ->assertSee("0 / {$total}");
});

it('persists a toggled item to the database', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $key = firstSeededItem($admin)->checklistKey();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('toggle', $key)
        ->assertSet("checkedKeys.{$key}", true);

    $progress = TestChecklistProgress::query()
        ->where('user_id', $admin->id)
        ->firstOrFail();

    expect($progress->checked_keys)->toBe([$key]);
});

it('unchecks a previously checked item when toggled twice', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $key = firstSeededItem($admin)->checklistKey();

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('toggle', $key)
        ->call('toggle', $key);

    expect($component->get('checkedKeys'))->toBe([]);

    $progress = TestChecklistProgress::query()
        ->where('user_id', $admin->id)
        ->firstOrFail();

    expect($progress->checked_keys)->toBe([]);
});

it('ignores attempts to toggle an unknown key', function () {
    $admin = adminChecklistUser();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('toggle', 'definitely-not-a-real-key')
        ->assertSet('checkedKeys', []);

    expect(TestChecklistProgress::query()->count())->toBe(0);
});

it('loads previously saved progress on mount', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $keys = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->orderBy('id')
        ->take(3)
        ->get()
        ->map(fn (TestChecklistItem $i): string => $i->checklistKey())
        ->all();

    TestChecklistProgress::query()->create([
        'user_id' => $admin->id,
        'checked_keys' => $keys,
    ]);

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page');

    foreach ($keys as $key) {
        $component->assertSet("checkedKeys.{$key}", true);
    }
});

it('drops stale keys that no longer match an existing row', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $validKey = firstSeededItem($admin)->checklistKey();

    TestChecklistProgress::query()->create([
        'user_id' => $admin->id,
        'checked_keys' => [$validKey, 'item-9999999', 'stale-key-12345'],
    ]);

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page');

    expect($component->get('checkedKeys'))->toBe([$validKey => true]);
});

it('reset all clears the checked keys and deletes the progress row', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $keys = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->take(5)
        ->get()
        ->map(fn (TestChecklistItem $i): string => $i->checklistKey())
        ->all();

    TestChecklistProgress::query()->create([
        'user_id' => $admin->id,
        'checked_keys' => $keys,
    ]);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('resetAll')
        ->assertSet('checkedKeys', []);

    expect(TestChecklistProgress::query()->where('user_id', $admin->id)->exists())
        ->toBeFalse();
});

it('reset all leaves admin-added items in place', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$section->slug()}--{$subsection->slug()}", 'Survives a reset')
        ->call('addItem', $section->slug(), $subsection->slug());

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('resetAll');

    expect(TestChecklistItem::query()->where('user_id', $admin->id)->where('text', 'Survives a reset')->exists())
        ->toBeTrue();
});

it('reset all leaves deleted items deleted', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('removeItem', $item->id);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('resetAll');

    expect(TestChecklistItem::query()->find($item->id))->toBeNull();
});

it('reset all leaves edited item text in place', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('startEdit', $item->id)
        ->set('editingText', 'Edited and kept')
        ->call('saveEdit');

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('resetAll');

    expect(TestChecklistItem::query()->find($item->id)?->text)->toBe('Edited and kept');
});

it('reset all leaves a custom item order in place', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    $ids = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    // Reverse the subsection order.
    $reversed = array_reverse($ids);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('reorderItems', $section->slug(), $subsection->slug(), $reversed)
        ->call('resetAll');

    $afterReset = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    expect($afterReset)->toBe($reversed);
});

it('exposes a reset-all button gated behind an Alpine modal', function () {
    $admin = adminChecklistUser();

    // Reset-all goes through an Alpine-powered confirmation modal instead of
    // the browser's native window.confirm() (which suffers from the
    // "prevent this page from creating additional dialogs" trap).
    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->assertSeeHtml('@click="confirmReset = true"')
        ->assertSeeHtml('Reset all progress?')
        ->assertSeeHtml('$wire.resetAll()')
        ->assertDontSeeHtml('wire:confirm="Uncheck');
});

it('exposes a remove-item button gated behind an Alpine modal', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = TestChecklistItem::query()->where('user_id', $admin->id)->first();

    // Per-item delete uses the same modal pattern: clicking the trash icon
    // opens a Tailwind/Alpine confirmation overlay rather than firing
    // window.confirm() directly via Livewire.
    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->assertSeeHtml('confirmDelete.itemId = '.$item->id.'; confirmDelete.open = true')
        ->assertSeeHtml('Remove item?')
        ->assertSeeHtml('$wire.removeItem(confirmDelete.itemId)')
        ->assertDontSeeHtml('wire:confirm="Remove this checklist item');
});

it('updates the progress counter as items are toggled', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $keys = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->take(3)
        ->get()
        ->map(fn (TestChecklistItem $i): string => $i->checklistKey())
        ->all();

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('toggle', $keys[0])
        ->call('toggle', $keys[1])
        ->call('toggle', $keys[2]);

    expect($component->instance()->checkedCount())->toBe(3);
});

it('renders a sidebar with an anchor link for every section', function () {
    $admin = adminChecklistUser();
    $checklist = app(ChecklistDefinition::class)->checklist();

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->assertSee('Jump to');

    foreach ($checklist->sections as $section) {
        $component->assertSeeHtml('href="#section-'.$section->slug().'"');
        $component->assertSeeHtml('id="section-'.$section->slug().'"');
    }
});

it('renders a sidebar subsection link for every subsection', function () {
    $admin = adminChecklistUser();
    $checklist = app(ChecklistDefinition::class)->checklist();

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page');

    foreach ($checklist->sections as $section) {
        foreach ($section->subsections as $subsection) {
            $anchor = $section->subsectionAnchorId($subsection);
            $component->assertSeeHtml('href="#subsection-'.$anchor.'"');
            $component->assertSeeHtml('id="subsection-'.$anchor.'"');
        }
    }
});

it('does not leak one user\'s progress into another user\'s session', function () {
    $adminA = adminChecklistUser();
    $adminB = billingReadyUser(['email' => 'admin-b@example.com']);
    AdminDesignation::factory()->create(['email' => $adminB->email]);

    seedChecklistFor($adminA);
    $key = firstSeededItem($adminA)->checklistKey();

    Livewire::actingAs($adminA)
        ->test('admin-test-checklist-page')
        ->call('toggle', $key);

    Livewire::actingAs($adminB)
        ->test('admin-test-checklist-page')
        ->assertSet('checkedKeys', []);
});

it('renders an add-item form inside every subsection', function () {
    $admin = adminChecklistUser();
    $checklist = app(ChecklistDefinition::class)->checklist();

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page');

    foreach ($checklist->sections as $section) {
        foreach ($section->subsections as $subsection) {
            $anchor = $section->subsectionAnchorId($subsection);
            $component->assertSeeHtml('data-test="checklist-add-item-form-'.$anchor.'"');
            // Use assertSee so subsection titles containing `&` (e.g.
            // "Profile & Account") are escaped to match the rendered HTML.
            $component->assertSee('Add item to '.$subsection->title);
        }
    }
});

it('lets an admin add an item under a specific subsection', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];
    $anchor = $section->subsectionAnchorId($subsection);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$anchor}", 'Verify the tip receipt email subject line')
        ->call('addItem', $section->slug(), $subsection->slug())
        ->assertHasNoErrors()
        ->assertSee('Verify the tip receipt email subject line');

    $stored = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderByDesc('position')
        ->first();

    expect($stored?->text)->toBe('Verify the tip receipt email subject line');
});

it('appends new items to the end of their subsection', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    $maxBefore = (int) TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->max('position');

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$section->slug()}--{$subsection->slug()}", 'Tail item')
        ->call('addItem', $section->slug(), $subsection->slug())
        ->assertHasNoErrors();

    $appended = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('text', 'Tail item')
        ->firstOrFail();

    expect($appended->position)->toBe($maxBefore + 1);
});

it('clears only the subsection-scoped input when an item is added', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];
    $anchor = $section->subsectionAnchorId($subsection);

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$anchor}", 'A new test scenario')
        ->call('addItem', $section->slug(), $subsection->slug());

    expect($component->get('newItemTexts'))->not->toHaveKey($anchor);
});

it('trims whitespace from new item text on save', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$section->slug()}--{$subsection->slug()}", '   Padded text   ')
        ->call('addItem', $section->slug(), $subsection->slug())
        ->assertHasNoErrors();

    $stored = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('text', 'Padded text')
        ->first();

    expect($stored)->not->toBeNull();
});

it('rejects empty new item text', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];
    $anchor = $section->subsectionAnchorId($subsection);
    $countBefore = TestChecklistItem::query()->where('user_id', $admin->id)->count();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$anchor}", '')
        ->call('addItem', $section->slug(), $subsection->slug())
        ->assertHasErrors("newItemTexts.{$anchor}");

    expect(TestChecklistItem::query()->where('user_id', $admin->id)->count())->toBe($countBefore);
});

it('rejects new item text shorter than 2 characters', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];
    $anchor = $section->subsectionAnchorId($subsection);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$anchor}", 'a')
        ->call('addItem', $section->slug(), $subsection->slug())
        ->assertHasErrors("newItemTexts.{$anchor}");
});

it('rejects new item text longer than 500 characters', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];
    $anchor = $section->subsectionAnchorId($subsection);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$anchor}", str_repeat('x', 501))
        ->call('addItem', $section->slug(), $subsection->slug())
        ->assertHasErrors("newItemTexts.{$anchor}");
});

it('ignores attempts to add an item to an unknown subsection', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $countBefore = TestChecklistItem::query()->where('user_id', $admin->id)->count();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set('newItemTexts.fake-section--fake-subsection', 'Hello world')
        ->call('addItem', 'fake-section', 'fake-subsection');

    expect(TestChecklistItem::query()->where('user_id', $admin->id)->count())->toBe($countBefore);
});

it('lets an admin remove a built-in seeded item', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('removeItem', $item->id);

    expect(TestChecklistItem::query()->where('id', $item->id)->exists())->toBeFalse();
});

it('lets an admin remove an item they added themselves', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$section->slug()}--{$subsection->slug()}", 'Throwaway')
        ->call('addItem', $section->slug(), $subsection->slug());

    $created = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('text', 'Throwaway')
        ->firstOrFail();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('removeItem', $created->id);

    expect(TestChecklistItem::query()->where('id', $created->id)->exists())->toBeFalse();
});

it('clears the checked state when an item is removed', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);
    $key = $item->checklistKey();

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('toggle', $key);

    expect($component->get('checkedKeys'))->toHaveKey($key);

    $component->call('removeItem', $item->id);

    expect($component->get('checkedKeys'))->not->toHaveKey($key);

    $progress = TestChecklistProgress::query()->where('user_id', $admin->id)->first();
    expect($progress?->checked_keys ?? [])->not->toContain($key);
});

it('refuses to remove another admin\'s item', function () {
    $adminA = adminChecklistUser();
    $adminB = billingReadyUser(['email' => 'admin-other@example.com']);
    AdminDesignation::factory()->create(['email' => $adminB->email]);

    seedChecklistFor($adminB);
    $itemB = firstSeededItem($adminB);

    Livewire::actingAs($adminA)
        ->test('admin-test-checklist-page')
        ->call('removeItem', $itemB->id);

    expect(TestChecklistItem::query()->where('id', $itemB->id)->exists())->toBeTrue();
});

it('lets an admin start editing any item', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('startEdit', $item->id);

    expect($component->get('editingItemId'))->toBe($item->id);
    expect($component->get('editingText'))->toBe($item->text);
});

it('cancels an in-progress edit and clears edit state', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('startEdit', $item->id)
        ->call('cancelEdit');

    expect($component->get('editingItemId'))->toBeNull();
    expect($component->get('editingText'))->toBe('');
});

it('saves an edited item to the database', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('startEdit', $item->id)
        ->set('editingText', 'Brand new wording for an existing item')
        ->call('saveEdit')
        ->assertHasNoErrors()
        ->assertSet('editingItemId', null);

    expect(TestChecklistItem::query()->find($item->id)->text)
        ->toBe('Brand new wording for an existing item');
});

it('preserves the checked state across an edit (key is stable)', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);
    $key = $item->checklistKey();

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('toggle', $key)
        ->call('startEdit', $item->id)
        ->set('editingText', 'Edited text')
        ->call('saveEdit');

    expect($component->get('checkedKeys'))->toHaveKey($key);
});

it('rejects edits that empty the text', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);
    $originalText = $item->text;

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('startEdit', $item->id)
        ->set('editingText', '')
        ->call('saveEdit')
        ->assertHasErrors('editingText');

    expect(TestChecklistItem::query()->find($item->id)->text)->toBe($originalText);
});

it('rejects edits longer than 500 characters', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('startEdit', $item->id)
        ->set('editingText', str_repeat('y', 501))
        ->call('saveEdit')
        ->assertHasErrors('editingText');
});

it('refuses to start editing another admin\'s item', function () {
    $adminA = adminChecklistUser();
    $adminB = billingReadyUser(['email' => 'admin-other-edit@example.com']);
    AdminDesignation::factory()->create(['email' => $adminB->email]);

    seedChecklistFor($adminB);
    $itemB = firstSeededItem($adminB);

    $component = Livewire::actingAs($adminA)
        ->test('admin-test-checklist-page')
        ->call('startEdit', $itemB->id);

    expect($component->get('editingItemId'))->toBeNull();
});

it('toggles a freshly added item just like a seeded one', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$section->slug()}--{$subsection->slug()}", 'Verify the song list paginates correctly')
        ->call('addItem', $section->slug(), $subsection->slug());

    $custom = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('text', 'Verify the song list paginates correctly')
        ->firstOrFail();
    $key = $custom->checklistKey();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('toggle', $key)
        ->assertSet("checkedKeys.{$key}", true);

    $progress = TestChecklistProgress::query()->where('user_id', $admin->id)->firstOrFail();
    expect($progress->checked_keys)->toContain($key);
});

it('counts newly added items in the totalItems progress denominator', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $base = TestChecklistItem::query()->where('user_id', $admin->id)->count();
    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$section->slug()}--{$subsection->slug()}", 'Custom #1')
        ->call('addItem', $section->slug(), $subsection->slug())
        ->set("newItemTexts.{$section->slug()}--{$subsection->slug()}", 'Custom #2')
        ->call('addItem', $section->slug(), $subsection->slug());

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page');

    expect($component->instance()->totalItems())->toBe($base + 2);
});

it('isolates checklist items between admins', function () {
    $adminA = adminChecklistUser();
    $adminB = billingReadyUser(['email' => 'admin-c@example.com']);
    AdminDesignation::factory()->create(['email' => $adminB->email]);

    seedChecklistFor($adminA);
    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    Livewire::actingAs($adminA)
        ->test('admin-test-checklist-page')
        ->set("newItemTexts.{$section->slug()}--{$subsection->slug()}", 'Admin A only secret')
        ->call('addItem', $section->slug(), $subsection->slug());

    Livewire::actingAs($adminB)
        ->test('admin-test-checklist-page')
        ->assertDontSee('Admin A only secret');
});

it('renders edit and delete buttons for every seeded item', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->assertSeeHtml('data-test="checklist-edit-'.$item->id.'"')
        ->assertSeeHtml('data-test="checklist-remove-'.$item->id.'"');
});

it('wires SortableJS into every subsection ul with matching anchor data-test', function () {
    $admin = adminChecklistUser();
    $checklist = app(ChecklistDefinition::class)->checklist();

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page');

    foreach ($checklist->sections as $section) {
        foreach ($section->subsections as $subsection) {
            $anchor = $section->subsectionAnchorId($subsection);
            $component->assertSeeHtml('data-test="checklist-items-list-'.$anchor.'"');
            $component->assertSeeHtml('x-data="checklistSortable(');
        }
    }
});

it('stamps data-reorder-id on every item row so SortableJS can identify them', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);
    $item = firstSeededItem($admin);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->assertSeeHtml('data-reorder-id="'.$item->id.'"');
});

it('reorders items within a subsection based on the dropped id list', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    $ids = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    // Move the last item to the front.
    $last = array_pop($ids);
    $newOrder = array_merge([$last], $ids);

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('reorderItems', $section->slug(), $subsection->slug(), $newOrder);

    $afterReorder = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    expect($afterReorder)->toBe($newOrder);
});

it('reorder writes sequential 0-based positions for the subsection', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    $ids = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('reorderItems', $section->slug(), $subsection->slug(), array_reverse($ids));

    $positions = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('position')
        ->all();

    expect($positions)->toBe(range(0, count($ids) - 1));
});

it('reorder leaves other subsections untouched', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $firstSubsection = $section->subsections[0];
    $secondSubsection = $section->subsections[1] ?? null;

    expect($secondSubsection)->not->toBeNull();

    $secondIdsBefore = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $secondSubsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    $firstIds = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $firstSubsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('reorderItems', $section->slug(), $firstSubsection->slug(), array_reverse($firstIds));

    $secondIdsAfter = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $secondSubsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    expect($secondIdsAfter)->toBe($secondIdsBefore);
});

it('reorder rejects an id that belongs to a different subsection', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $firstSubsection = $section->subsections[0];
    $secondSubsection = $section->subsections[1] ?? null;
    expect($secondSubsection)->not->toBeNull();

    $firstIds = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $firstSubsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    $strangerId = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $secondSubsection->title)
        ->value('id');

    // Poison the payload with an id from another subsection.
    $poisoned = [...$firstIds, $strangerId];

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('reorderItems', $section->slug(), $firstSubsection->slug(), $poisoned);

    $firstIdsAfter = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $firstSubsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    // The subsection is untouched because the whole reorder was aborted.
    expect($firstIdsAfter)->toBe($firstIds);
});

it('reorder ignores an empty id list', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    $before = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('reorderItems', $section->slug(), $subsection->slug(), []);

    $after = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    expect($after)->toBe($before);
});

it('reorder ignores an unknown subsection slug', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    $before = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('reorderItems', 'not-a-section', 'not-a-subsection', array_reverse($before));

    $after = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    expect($after)->toBe($before);
});

it('reorder refuses to touch another admin\'s items', function () {
    $adminA = adminChecklistUser();
    $adminB = billingReadyUser(['email' => 'admin-reorder@example.com']);
    AdminDesignation::factory()->create(['email' => $adminB->email]);

    seedChecklistFor($adminB);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    $bIdsBefore = TestChecklistItem::query()
        ->where('user_id', $adminB->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    // adminA tries to reorder adminB's items by passing their ids.
    Livewire::actingAs($adminA)
        ->test('admin-test-checklist-page')
        ->call('reorderItems', $section->slug(), $subsection->slug(), array_reverse($bIdsBefore));

    $bIdsAfter = TestChecklistItem::query()
        ->where('user_id', $adminB->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    expect($bIdsAfter)->toBe($bIdsBefore);
});

it('emits the checked attribute on the right boxes on a cold mount', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    // Pick three random rows and save them to progress.
    $items = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->inRandomOrder()
        ->take(3)
        ->get();

    $keys = $items->map(fn (TestChecklistItem $i): string => $i->checklistKey())->all();
    TestChecklistProgress::query()->create([
        'user_id' => $admin->id,
        'checked_keys' => $keys,
    ]);

    // Fresh component mount — same thing Livewire does on a full page load.
    $component = Livewire::actingAs($admin)->test('admin-test-checklist-page');
    $html = $component->html();

    // Counter reflects the three keys.
    expect($html)->toContain('3 / ');

    // Every stored key gets a checked attribute on its matching input.
    foreach ($keys as $key) {
        $escapedKey = preg_quote($key, '/');
        $pattern = '/<input[^>]*data-test="checklist-item-'.$escapedKey.'"[^>]*>/';
        expect($html)->toMatch($pattern);

        preg_match($pattern, $html, $match);
        expect($match[0] ?? '')->toContain('checked');
    }
});

it('does not emit the checked attribute on unchecked boxes on a cold mount', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $items = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->orderBy('id')
        ->take(2)
        ->get();

    $checkedKey = $items[0]->checklistKey();
    $uncheckedKey = $items[1]->checklistKey();

    TestChecklistProgress::query()->create([
        'user_id' => $admin->id,
        'checked_keys' => [$checkedKey],
    ]);

    $html = Livewire::actingAs($admin)->test('admin-test-checklist-page')->html();

    // The checked one HAS checked.
    $checkedPattern = '/<input[^>]*data-test="checklist-item-'.preg_quote($checkedKey, '/').'"[^>]*>/';
    preg_match($checkedPattern, $html, $checkedMatch);
    expect($checkedMatch[0] ?? '')->toContain('checked');

    // The unchecked one does NOT.
    $uncheckedPattern = '/<input[^>]*data-test="checklist-item-'.preg_quote($uncheckedKey, '/').'"[^>]*>/';
    preg_match($uncheckedPattern, $html, $uncheckedMatch);
    expect($uncheckedMatch[0] ?? '')->not->toContain('checked');
});

it('reorder preserves the checked state of every row (keys are stable)', function () {
    $admin = adminChecklistUser();
    seedChecklistFor($admin);

    $section = app(ChecklistDefinition::class)->checklist()->sections[0];
    $subsection = $section->subsections[0];

    $ids = TestChecklistItem::query()
        ->where('user_id', $admin->id)
        ->where('section_title', $section->title)
        ->where('subsection_title', $subsection->title)
        ->orderBy('position')
        ->pluck('id')
        ->all();

    $key = 'item-'.$ids[0];

    $component = Livewire::actingAs($admin)
        ->test('admin-test-checklist-page')
        ->call('toggle', $key)
        ->call('reorderItems', $section->slug(), $subsection->slug(), array_reverse($ids));

    expect($component->get('checkedKeys'))->toHaveKey($key);
});
