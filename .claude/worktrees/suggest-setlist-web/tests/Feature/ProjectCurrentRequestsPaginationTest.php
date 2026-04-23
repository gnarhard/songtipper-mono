<?php

declare(strict_types=1);

use App\Models\AudienceProfile;
use App\Models\Project;
use App\Models\Request as SongRequest;
use App\Models\Song;
use App\Models\User;
use Livewire\Livewire;

function bootPreviouslyPlayedScenario(int $totalPlayed): array
{
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'is_accepting_requests' => true,
    ]);

    $profile = AudienceProfile::factory()->create([
        'project_id' => $project->id,
        'visitor_token' => 'aud-previously-played-pagination',
    ]);

    /** @var array<int, SongRequest> $requests */
    $requests = [];

    for ($index = 0; $index < $totalPlayed; $index++) {
        $song = Song::factory()->create([
            'title' => sprintf('Played Song %02d', $index + 1),
        ]);

        $requests[] = SongRequest::factory()->played()->create([
            'project_id' => $project->id,
            'audience_profile_id' => $profile->id,
            'song_id' => $song->id,
            'played_at' => now()->subMinutes($totalPlayed - $index),
        ]);
    }

    return [
        'project' => $project,
        'profile' => $profile,
        'requests' => $requests,
    ];
}

it('shows at most 3 previously played requests per page', function () {
    $scenario = bootPreviouslyPlayedScenario(totalPlayed: 7);
    /** @var Project $project */
    $project = $scenario['project'];

    Livewire::withCookies([
        'songtipper_audience_token' => 'aud-previously-played-pagination',
    ])
        ->test('project-current-requests', ['project' => $project])
        ->assertSee('Previously played')
        ->assertSee('Played Song 07')
        ->assertSee('Played Song 06')
        ->assertSee('Played Song 05')
        ->assertDontSee('Played Song 04')
        ->assertDontSee('Played Song 03')
        ->assertDontSee('Played Song 02')
        ->assertDontSee('Played Song 01');
});

it('renders pagination controls when there are more than 3 previously played requests', function () {
    $scenario = bootPreviouslyPlayedScenario(totalPlayed: 4);
    /** @var Project $project */
    $project = $scenario['project'];

    Livewire::withCookies([
        'songtipper_audience_token' => 'aud-previously-played-pagination',
    ])
        ->test('project-current-requests', ['project' => $project])
        ->assertSee('Page 1 of 2')
        ->assertSee('Newer')
        ->assertSee('Older');
});

it('does not render pagination controls when previously played fits on a single page', function () {
    $scenario = bootPreviouslyPlayedScenario(totalPlayed: 3);
    /** @var Project $project */
    $project = $scenario['project'];

    Livewire::withCookies([
        'songtipper_audience_token' => 'aud-previously-played-pagination',
    ])
        ->test('project-current-requests', ['project' => $project])
        ->assertSee('Previously played')
        ->assertDontSee('Page 1 of')
        ->assertDontSee('Newer')
        ->assertDontSee('Older');
});

it('advances to the next page of previously played requests', function () {
    $scenario = bootPreviouslyPlayedScenario(totalPlayed: 7);
    /** @var Project $project */
    $project = $scenario['project'];

    Livewire::withCookies([
        'songtipper_audience_token' => 'aud-previously-played-pagination',
    ])
        ->test('project-current-requests', ['project' => $project])
        ->call('nextPage', 'playedPage')
        ->assertSee('Page 2 of 3')
        ->assertSee('Played Song 04')
        ->assertSee('Played Song 03')
        ->assertSee('Played Song 02')
        ->assertDontSee('Played Song 07')
        ->assertDontSee('Played Song 06')
        ->assertDontSee('Played Song 05')
        ->assertDontSee('Played Song 01');
});

it('returns to the previous page of previously played requests', function () {
    $scenario = bootPreviouslyPlayedScenario(totalPlayed: 7);
    /** @var Project $project */
    $project = $scenario['project'];

    Livewire::withCookies([
        'songtipper_audience_token' => 'aud-previously-played-pagination',
    ])
        ->test('project-current-requests', ['project' => $project])
        ->call('nextPage', 'playedPage')
        ->call('previousPage', 'playedPage')
        ->assertSee('Page 1 of 3')
        ->assertSee('Played Song 07')
        ->assertSee('Played Song 06')
        ->assertSee('Played Song 05')
        ->assertDontSee('Played Song 04');
});
