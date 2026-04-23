<?php

declare(strict_types=1);

use App\Models\AppReleasePolicy;

dataset('app release platforms', AppReleasePolicy::platforms());

it('returns the enabled version policy for the requested platform', function (string $platform) {
    $policy = AppReleasePolicy::factory()
        ->forPlatform($platform)
        ->enabled()
        ->create([
            'latest_version' => '2.4.6',
            'latest_build_number' => 81,
        ]);

    $this->getJson("/api/v1/app/version-policy?platform={$platform}")
        ->assertOk()
        ->assertJsonPath('data.platform', $platform)
        ->assertJsonPath('data.latest_version', '2.4.6')
        ->assertJsonPath('data.latest_build_number', 81)
        ->assertJsonPath('data.store_url', $policy->store_url)
        ->assertJsonPath('data.archive_url', $policy->archive_url);
})->with('app release platforms');

it('returns 404 when the requested platform policy is disabled', function (string $platform) {
    AppReleasePolicy::factory()
        ->forPlatform($platform)
        ->disabled()
        ->create();

    $this->getJson("/api/v1/app/version-policy?platform={$platform}")
        ->assertNotFound();
})->with('app release platforms');

it('returns 404 when no version policy exists for the requested platform', function (string $platform) {
    $this->getJson("/api/v1/app/version-policy?platform={$platform}")
        ->assertNotFound();
})->with('app release platforms');
