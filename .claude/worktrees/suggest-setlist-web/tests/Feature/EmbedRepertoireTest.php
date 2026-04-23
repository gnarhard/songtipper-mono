<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Song;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Embed widget rendering
|--------------------------------------------------------------------------
|
| The public repertoire view (?embed=1) is dropped into third-party sites
| via an iframe. The embed must be read-only browsing — no buttons that
| trigger server actions and no anchors that would navigate the iframe
| away from the embed view.
|
| See .agent-rules/60-embed-widgets.md for the contract.
*/

function embedRepertoireProject(): Project
{
    $owner = User::factory()->create();

    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => true,
        'is_accepting_original_requests' => true,
        'is_accepting_tips' => true,
        'performer_info_url' => 'https://example.test/about',
    ]);

    $song = Song::factory()->create([
        'title' => 'Embedded Anthem',
        'artist' => 'Iframe Band',
        'era' => '90s',
        'genre' => 'Pop',
    ]);

    $project->projectSongs()->create([
        'song_id' => $song->id,
    ]);

    return $project;
}

it('does not render server-action wire:click buttons in embed mode', function () {
    $this->withoutVite();
    $project = embedRepertoireProject();

    $this->get(route('project.repertoire', [
        'projectSlug' => $project->slug,
        'embed' => 1,
    ]))
        ->assertSuccessful()
        ->assertDontSee('wire:click="surpriseMe"', false)
        ->assertDontSee('wire:click="clearFilters"', false)
        ->assertDontSee('IDK, Surprise Me', false);
});

it('does not render outbound navigation anchors in embed mode', function () {
    $this->withoutVite();
    $project = embedRepertoireProject();
    $song = $project->projectSongs()->first()->song;

    $response = $this->get(route('project.repertoire', [
        'projectSlug' => $project->slug,
        'embed' => 1,
    ]))->assertSuccessful();

    $response->assertDontSee(route('request.page', [
        'projectSlug' => $project->slug,
        'song' => $song->id,
    ]), false);

    $response->assertDontSee(route('project.learn-more', [
        'projectSlug' => $project->slug,
    ]), false);

    $response->assertDontSee('Learn More About the Performer', false);
    $response->assertDontSee('>Request<', false);
});

it('does not render forms in embed mode', function () {
    $this->withoutVite();
    $project = embedRepertoireProject();

    $this->get(route('project.repertoire', [
        'projectSlug' => $project->slug,
        'embed' => 1,
    ]))
        ->assertSuccessful()
        ->assertDontSee('<form', false)
        ->assertDontSee('wire:submit', false);
});

it('renders the embed without the site footer or auth links', function () {
    $this->withoutVite();
    $project = embedRepertoireProject();

    $this->get(route('project.repertoire', [
        'projectSlug' => $project->slug,
        'embed' => 1,
    ]))
        ->assertSuccessful()
        ->assertDontSee(route('login'), false)
        ->assertDontSee(route('register'), false)
        ->assertDontSee(route('dashboard'), false);
});

it('still renders the allowed read-only filter and pagination affordances in embed mode', function () {
    $this->withoutVite();
    $project = embedRepertoireProject();

    $this->get(route('project.repertoire', [
        'projectSlug' => $project->slug,
        'embed' => 1,
    ]))
        ->assertSuccessful()
        ->assertSee('Filter Songs', false)
        ->assertSee('placeholder="Search Title"', false)
        ->assertSee('placeholder="Search Artist"', false)
        ->assertSee('wire:model.live.debounce.300ms="title"', false)
        ->assertSee('Embedded Anthem');
});

it('opens the powered-by attribution in a new tab so it does not break the iframe', function () {
    $this->withoutVite();
    $project = embedRepertoireProject();

    $this->get(route('project.repertoire', [
        'projectSlug' => $project->slug,
        'embed' => 1,
    ]))
        ->assertSuccessful()
        ->assertSee('Powered by', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener noreferrer"', false);
});

it('allows iframe embedding only when the embed query parameter is present', function () {
    $this->withoutVite();
    $project = embedRepertoireProject();

    $embedded = $this->get(route('project.repertoire', [
        'projectSlug' => $project->slug,
        'embed' => 1,
    ]));
    $embedded->assertSuccessful();
    expect($embedded->headers->get('X-Frame-Options'))->toBeNull();
    expect($embedded->headers->get('Content-Security-Policy'))->toBe('frame-ancestors *');

    $standalone = $this->get(route('project.repertoire', [
        'projectSlug' => $project->slug,
    ]));
    $standalone->assertSuccessful();
    expect($standalone->headers->get('Content-Security-Policy'))->toBeNull();
});

it('renders the site footer chrome when the same page is loaded without embed mode', function () {
    $this->withoutVite();
    $project = embedRepertoireProject();

    // Sanity check: the standalone repertoire page DOES render the site footer
    // (and its auth links), so the embed assertions above are meaningful — the
    // embed view actively suppresses chrome that the standalone view ships.
    $this->get(route('project.repertoire', ['projectSlug' => $project->slug]))
        ->assertSuccessful()
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false);
});
