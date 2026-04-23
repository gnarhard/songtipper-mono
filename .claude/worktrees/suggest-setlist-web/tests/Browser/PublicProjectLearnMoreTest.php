<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSong;
use App\Models\Song;
use App\Models\User;

describe('Public project page browser flows', function () {
    it('redirects learn-more to project page when performer has no info URL', function () {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $owner->id,
            'slug' => 'no-info-project',
            'performer_info_url' => null,
        ]);

        $page = visit("/project/{$project->slug}/learn-more");

        $page->assertPathIs("/project/{$project->slug}")
            ->assertNoJavaScriptErrors();
    });

    it('shows empty repertoire state when project has no songs', function () {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $owner->id,
            'slug' => 'empty-project',
            'is_accepting_requests' => true,
        ]);

        $page = visit("/project/{$project->slug}");

        $page->assertSee($project->name)
            ->assertNoJavaScriptErrors();
    });

    it('displays instrumental badge for instrumental songs', function () {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $owner->id,
            'slug' => 'instrumental-project',
            'is_accepting_requests' => true,
        ]);
        $song = Song::factory()->create([
            'title' => 'Classical Piece',
            'artist' => 'Beethoven',
        ]);
        ProjectSong::factory()->create([
            'project_id' => $project->id,
            'song_id' => $song->id,
            'instrumental' => true,
        ]);

        $page = visit("/project/{$project->slug}");

        $page->assertSee('Classical Piece (instrumental)')
            ->assertNoJavaScriptErrors();
    });

    it('shows multiple songs in the repertoire list', function () {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $owner->id,
            'slug' => 'multi-song-project',
            'is_accepting_requests' => true,
        ]);
        $songs = [
            Song::factory()->create(['title' => 'Alpha Song', 'artist' => 'A Artist']),
            Song::factory()->create(['title' => 'Beta Song', 'artist' => 'B Artist']),
            Song::factory()->create(['title' => 'Gamma Song', 'artist' => 'C Artist']),
        ];
        foreach ($songs as $song) {
            ProjectSong::factory()->create([
                'project_id' => $project->id,
                'song_id' => $song->id,
            ]);
        }

        $page = visit("/project/{$project->slug}");

        $page->assertSee('Alpha Song')
            ->assertSee('Beta Song')
            ->assertSee('Gamma Song')
            ->assertNoJavaScriptErrors();
    });

    it('shows learn-more link when performer has info URL set', function () {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'owner_user_id' => $owner->id,
            'slug' => 'info-project',
            'performer_info_url' => 'https://example.com/performer',
            'is_accepting_requests' => true,
        ]);
        $song = Song::factory()->create(['title' => 'Some Song']);
        ProjectSong::factory()->create([
            'project_id' => $project->id,
            'song_id' => $song->id,
        ]);

        $page = visit("/project/{$project->slug}");

        $page->assertSee('Learn More About the Performer')
            ->assertNoJavaScriptErrors();
    });
});
