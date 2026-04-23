<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Cache\RateLimiter;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->projectA = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
        'slug' => 'throttle-project-a',
    ]);
    $this->projectB = Project::factory()->create([
        'owner_user_id' => $this->owner->id,
        'slug' => 'throttle-project-b',
    ]);

    clearPublicAudienceThrottle();
});

afterEach(function () {
    clearPublicAudienceThrottle();
});

function clearPublicAudienceThrottle(): void
{
    $limiter = app(RateLimiter::class);
    foreach (['public-audience-burst:127.0.0.1', 'public-audience-sustained:127.0.0.1'] as $key) {
        // Named-limiter cache keys are wrapped as md5(limiterName . limit->key).
        $limiter->clear(md5('public-audience'.$key));
    }
}

it('applies the public-audience throttle across different project slugs for the same ip', function () {
    // The named limiter allows 60 per minute per IP across all public routes,
    // regardless of which project slug is being hit.
    for ($i = 0; $i < 60; $i++) {
        $slug = $i % 2 === 0 ? $this->projectA->slug : $this->projectB->slug;
        $this->getJson("/api/v1/public/projects/{$slug}/repertoire")
            ->assertOk();
    }

    $this->getJson("/api/v1/public/projects/{$this->projectA->slug}/repertoire")
        ->assertStatus(429);

    $this->getJson("/api/v1/public/projects/{$this->projectB->slug}/repertoire")
        ->assertStatus(429);
});
