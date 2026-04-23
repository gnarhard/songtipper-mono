<?php

declare(strict_types=1);

use App\Support\TestChecklist\Checklist;
use App\Support\TestChecklist\ChecklistDefinition;
use App\Support\TestChecklist\ChecklistItem;
use App\Support\TestChecklist\ChecklistSection;
use App\Support\TestChecklist\ChecklistSubsection;
use App\Support\TestChecklist\ManualTestChecklist;

it('implements the ChecklistDefinition contract', function () {
    expect(new ManualTestChecklist)->toBeInstanceOf(ChecklistDefinition::class);
});

it('returns a Checklist aggregate', function () {
    $definition = new ManualTestChecklist;

    expect($definition->checklist())->toBeInstanceOf(Checklist::class);
});

it('caches the checklist between calls', function () {
    $definition = new ManualTestChecklist;

    expect($definition->checklist())->toBe($definition->checklist());
});

it('exposes the required top-level sections', function () {
    $titles = array_map(
        fn (ChecklistSection $section) => $section->title,
        (new ManualTestChecklist)->checklist()->sections,
    );

    expect($titles)->toContain('WEB APP')
        ->and($titles)->toContain('MOBILE APP (Flutter)')
        ->and($titles)->toContain('Public Pages')
        ->and($titles)->toContain('API-SPECIFIC VALIDATIONS');
});

it('produces at least one item per subsection', function () {
    foreach ((new ManualTestChecklist)->checklist()->sections as $section) {
        foreach ($section->subsections as $subsection) {
            expect($subsection->items)
                ->not->toBeEmpty("Subsection '{$subsection->title}' in '{$section->title}' has no items");
        }
    }
});

it('generates unique keys for every item across the whole checklist', function () {
    $checklist = (new ManualTestChecklist)->checklist();
    $keys = $checklist->allKeys();

    expect(count($keys))->toBe(count(array_unique($keys)));
});

it('assigns non-empty text to every item', function () {
    foreach ((new ManualTestChecklist)->checklist()->sections as $section) {
        foreach ($section->subsections as $subsection) {
            foreach ($subsection->items as $item) {
                expect(trim($item->text))->not->toBe('');
            }
        }
    }
});

it('reports a totalItems count that matches the sum of all subsection items', function () {
    $checklist = (new ManualTestChecklist)->checklist();

    $sum = 0;
    foreach ($checklist->sections as $section) {
        $sum += $section->itemCount();
    }

    expect($checklist->totalItems())->toBe($sum);
});

it('exposes a substantial number of items', function () {
    $checklist = (new ManualTestChecklist)->checklist();

    expect($checklist->totalItems())->toBeGreaterThan(100);
});

it('matches known keys via hasKey and filterValidKeys', function () {
    $checklist = (new ManualTestChecklist)->checklist();
    $allKeys = $checklist->allKeys();
    $firstKey = $allKeys[0];

    expect($checklist->hasKey($firstKey))->toBeTrue();
    expect($checklist->hasKey('not-a-real-key'))->toBeFalse();

    $filtered = $checklist->filterValidKeys([$firstKey, 'not-a-real-key', 123, null]);

    expect($filtered)->toBe([$firstKey]);
});

it('deduplicates values returned from filterValidKeys', function () {
    $checklist = (new ManualTestChecklist)->checklist();
    $allKeys = $checklist->allKeys();
    $key = $allKeys[0];

    expect($checklist->filterValidKeys([$key, $key, $key]))->toBe([$key]);
});

it('derives stable keys that depend on the section path and text', function () {
    $a = ChecklistItem::deriveKey('SECTION', 'Subsection', 'Check the thing');
    $b = ChecklistItem::deriveKey('SECTION', 'Subsection', 'Check the thing');
    $c = ChecklistItem::deriveKey('SECTION', 'Subsection', 'Check the other thing');
    $d = ChecklistItem::deriveKey('OTHER', 'Subsection', 'Check the thing');

    expect($a)->toBe($b);
    expect($a)->not->toBe($c);
    expect($a)->not->toBe($d);
    expect(strlen($a))->toBe(16);
});

it('derives a url-safe slug for each section', function () {
    foreach ((new ManualTestChecklist)->checklist()->sections as $section) {
        $slug = $section->slug();
        expect($slug)->not->toBe('');
        expect($slug)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
    }
});

it('produces unique section slugs across the whole checklist', function () {
    $slugs = array_map(
        fn (ChecklistSection $section) => $section->slug(),
        (new ManualTestChecklist)->checklist()->sections,
    );

    expect(count($slugs))->toBe(count(array_unique($slugs)));
});

it('produces unique subsection anchor ids within each section', function () {
    foreach ((new ManualTestChecklist)->checklist()->sections as $section) {
        $anchors = array_map(
            fn (ChecklistSubsection $subsection) => $section->subsectionAnchorId($subsection),
            $section->subsections,
        );

        expect(count($anchors))->toBe(
            count(array_unique($anchors)),
            "Duplicate subsection anchor ids in section '{$section->title}'",
        );
    }
});

it('produces globally unique subsection anchor ids across the whole checklist', function () {
    $anchors = [];
    foreach ((new ManualTestChecklist)->checklist()->sections as $section) {
        foreach ($section->subsections as $subsection) {
            $anchors[] = $section->subsectionAnchorId($subsection);
        }
    }

    expect(count($anchors))->toBe(count(array_unique($anchors)));
});

it('namespaces subsection anchor ids under the section slug', function () {
    $section = new ChecklistSection('WEB APP', [
        new ChecklistSubsection('Authentication', []),
    ]);

    expect($section->slug())->toBe('web-app');
    expect($section->subsectionAnchorId($section->subsections[0]))->toBe('web-app--authentication');
});

it('supports building an ad-hoc checklist for custom definitions', function () {
    $custom = new Checklist([
        new ChecklistSection('Section', [
            new ChecklistSubsection('Sub', [
                ChecklistItem::make('Section', 'Sub', 'One'),
                ChecklistItem::make('Section', 'Sub', 'Two'),
            ]),
        ]),
    ]);

    expect($custom->totalItems())->toBe(2);
    expect($custom->sections[0]->itemCount())->toBe(2);
    expect($custom->sections[0]->subsections[0]->itemCount())->toBe(2);
});
