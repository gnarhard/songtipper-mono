<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;

it('redirects to the project page when no performer info url is set', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'my-band',
        'performer_info_url' => null,
    ]);

    $response = $this->get("/project/{$project->slug}/learn-more");

    $response->assertRedirect(route('project.page', ['projectSlug' => $project->slug]));
});

it('redirects to external performer info url when set', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'external-band',
        'performer_info_url' => 'https://example.com/band-info',
    ]);

    $response = $this->get("/project/{$project->slug}/learn-more");

    $response->assertRedirect('https://example.com/band-info');
});

it('redirects to project page when performer info url is an empty string', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'empty-url-band',
        'performer_info_url' => '',
    ]);

    $response = $this->get("/project/{$project->slug}/learn-more");

    $response->assertRedirect(route('project.page', ['projectSlug' => $project->slug]));
});
