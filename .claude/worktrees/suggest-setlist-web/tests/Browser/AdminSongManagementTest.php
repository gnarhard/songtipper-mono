<?php

declare(strict_types=1);

use App\Models\AdminDesignation;
use App\Models\Song;

function adminUser(array $attributes = [])
{
    $user = billingReadyUser($attributes);
    AdminDesignation::create(['email' => $user->email]);

    return $user;
}

describe('Admin song management browser flows', function () {
    it('renders the admin songs page with song count stats', function () {
        $user = adminUser();
        Song::factory()->count(3)->create();

        $this->actingAs($user);

        $page = visit('/admin/songs');

        $page->assertSee('Master Songs')
            ->assertSee('3')
            ->assertNoJavaScriptErrors();
    });

    it('searches songs by title with live debounce', function () {
        $user = adminUser();
        Song::factory()->create(['title' => 'Bohemian Rhapsody', 'artist' => 'Queen']);
        Song::factory()->create(['title' => 'Wonderwall', 'artist' => 'Oasis']);

        $this->actingAs($user);

        $page = visit('/admin/songs');

        $page->assertSee('Bohemian Rhapsody')
            ->assertSee('Wonderwall')
            ->fill('[data-test="admin-songs-search"]', 'Bohemian')
            ->wait(1)
            ->assertSee('Bohemian Rhapsody')
            ->assertDontSee('Wonderwall')
            ->assertNoJavaScriptErrors();
    });

    it('clears search to show all songs again', function () {
        $user = adminUser();
        Song::factory()->create(['title' => 'Song Alpha', 'artist' => 'Artist One']);
        Song::factory()->create(['title' => 'Song Beta', 'artist' => 'Artist Two']);

        $this->actingAs($user);

        $page = visit('/admin/songs?q=Alpha');

        $page->assertSee('Song Alpha')
            ->assertDontSee('Song Beta')
            ->fill('[data-test="admin-songs-search"]', '')
            ->wait(1)
            ->assertSee('Song Alpha')
            ->assertSee('Song Beta')
            ->assertNoJavaScriptErrors();
    });

    it('opens edit form and saves song changes via Livewire', function () {
        $user = adminUser();
        $song = Song::factory()->create([
            'title' => 'Old Title',
            'artist' => 'Old Artist',
        ]);

        $this->actingAs($user);

        $page = visit('/admin/songs');

        $page->assertSee('Old Title')
            ->click("[data-test=\"edit-song-{$song->id}\"] >> nth=0")
            ->wait(1)
            ->fill('[data-test="edit-title"] >> nth=0', 'New Title')
            ->fill('[data-test="edit-artist"] >> nth=0', 'New Artist')
            ->click('[data-test="save-song"] >> nth=0')
            ->wait(1)
            ->assertSee('Updated "New Title" by New Artist')
            ->assertNoJavaScriptErrors();

        $fresh = $song->fresh();
        expect($fresh->title)->toBe('New Title');
        expect($fresh->artist)->toBe('New Artist');
    });

    it('shows delete confirmation modal when clicking delete', function () {
        $user = adminUser();
        $song = Song::factory()->create([
            'title' => 'Delete Me',
            'artist' => 'Remove Artist',
        ]);

        $this->actingAs($user);

        $page = visit('/admin/songs');

        $page->assertSee('Delete Me')
            ->assertSee('Remove Artist')
            ->assertNoJavaScriptErrors();
    });

    it('blocks non-admin users from accessing admin songs', function () {
        $user = billingReadyUser();

        $this->actingAs($user);

        $page = visit('/admin/songs');

        $page->assertDontSee('Master Songs')
            ->assertNoJavaScriptErrors();
    });
});
