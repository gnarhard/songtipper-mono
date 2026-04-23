<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Song;
use App\Models\User;

it('applies the shared Tailwind shell across the public pages without legacy theme classes', function () {
    $this->withoutVite();

    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => true,
        'is_accepting_original_requests' => false,
    ]);
    $song = Song::factory()->create();

    $project->projectSongs()->create([
        'song_id' => $song->id,
    ]);

    $sharedBodyClasses = 'min-h-screen bg-canvas-light font-sans antialiased text-ink dark:bg-canvas-dark dark:text-ink-inverse';
    $legacyClasses = [
        'app-shell',
        'app-panel',
        'app-card',
        'app-primary-button',
    ];

    $homeResponse = $this->get(route('home'));
    $projectResponse = $this->get(route('project.page', ['projectSlug' => $project->slug]));
    $requestResponse = $this->get(route('request.page', [
        'projectSlug' => $project->slug,
        'song' => $song->id,
    ]));

    foreach ([$homeResponse, $projectResponse, $requestResponse] as $response) {
        $response->assertSuccessful()
            ->assertSee($sharedBodyClasses, false);

        foreach ($legacyClasses as $legacyClass) {
            $response->assertDontSee($legacyClass, false);
        }
    }
});

it('keeps the app stylesheet limited to Tailwind directives plus shared heading typography', function () {
    $stylesheet = file_get_contents(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain('@tailwind base;')
        ->toContain('@tailwind components;')
        ->toContain('@tailwind utilities;')
        ->toContain('@layer base {')
        ->toContain('@apply font-display;');
});

it('keeps brand text off brand backgrounds in light mode by switching those combinations to accent text', function () {
    $stylesheet = file_get_contents(resource_path('css/app.css'));

    expect($stylesheet)
        ->toContain('@media not (prefers-color-scheme: dark)')
        ->toContain('[class*="bg-brand"][class*="text-brand"]')
        ->toContain('[class*="bg-brand"] [class*="text-brand"]')
        ->toContain('[class*="hover:bg-brand"][class*="text-brand"]:hover')
        ->toContain('[class*="focus:bg-brand"][class*="text-brand"]:focus')
        ->toContain('@apply text-accent;');
});

it('loads the shared heading font weights for Raleway', function () {
    expect(file_get_contents(resource_path('views/partials/fonts.blade.php')))
        ->toContain('raleway:400,500,600,700');
});
