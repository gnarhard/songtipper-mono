<?php

declare(strict_types=1);

use App\Models\AdminDesignation;
use App\Models\AppReleasePolicy;
use Livewire\Livewire;

dataset('mobile release platforms', AppReleasePolicy::mobilePlatforms());
dataset('desktop release platforms', AppReleasePolicy::desktopPlatforms());

function releasePlatformLabel(string $platform): string
{
    return match ($platform) {
        AppReleasePolicy::PLATFORM_IOS => 'iOS',
        AppReleasePolicy::PLATFORM_MACOS => 'macOS',
        AppReleasePolicy::PLATFORM_ANDROID => 'Android',
        AppReleasePolicy::PLATFORM_WINDOWS => 'Windows',
        AppReleasePolicy::PLATFORM_LINUX => 'Linux',
        default => $platform,
    };
}

it('shows the app release policy panel on the admin access page', function () {
    $adminUser = billingReadyUser([
        'email' => 'admin@example.com',
    ]);

    AdminDesignation::factory()->create([
        'email' => $adminUser->email,
    ]);

    Livewire::actingAs($adminUser)
        ->test('admin-access-page')
        ->assertSeeLivewire('app-release-policy-panel');
});

it('lets an admin save a mobile release policy', function (string $platform) {
    $adminUser = billingReadyUser([
        'email' => 'admin@example.com',
    ]);

    AdminDesignation::factory()->create([
        'email' => $adminUser->email,
    ]);

    $storeUrl = "https://example.com/{$platform}";

    Livewire::actingAs($adminUser)
        ->test('app-release-policy-panel')
        ->set("policies.{$platform}.latest_version", '1.4.0')
        ->set("policies.{$platform}.latest_build_number", 42)
        ->set("policies.{$platform}.store_url", $storeUrl)
        ->set("policies.{$platform}.is_enabled", true)
        ->call('save', $platform)
        ->assertHasNoErrors()
        ->assertSee('Saved '.releasePlatformLabel($platform).' release policy.');

    $policy = AppReleasePolicy::query()
        ->where('platform', $platform)
        ->firstOrFail();

    expect($policy->latest_version)->toBe('1.4.0');
    expect($policy->latest_build_number)->toBe(42);
    expect($policy->store_url)->toBe($storeUrl);
    expect($policy->archive_url)->toBeNull();
    expect($policy->is_enabled)->toBeTrue();
})->with('mobile release platforms');

it('lets an admin save a desktop release policy', function (string $platform) {
    $adminUser = billingReadyUser([
        'email' => 'admin@example.com',
    ]);

    AdminDesignation::factory()->create([
        'email' => $adminUser->email,
    ]);

    $archiveUrl = "https://downloads.example.com/{$platform}/app-archive.json";

    Livewire::actingAs($adminUser)
        ->test('app-release-policy-panel')
        ->set("policies.{$platform}.latest_version", '1.4.0')
        ->set("policies.{$platform}.latest_build_number", 42)
        ->set("policies.{$platform}.archive_url", $archiveUrl)
        ->set("policies.{$platform}.is_enabled", true)
        ->call('save', $platform)
        ->assertHasNoErrors()
        ->assertSee('Saved '.releasePlatformLabel($platform).' release policy.');

    $policy = AppReleasePolicy::query()
        ->where('platform', $platform)
        ->firstOrFail();

    expect($policy->latest_version)->toBe('1.4.0');
    expect($policy->latest_build_number)->toBe(42);
    expect($policy->store_url)->toBeNull();
    expect($policy->archive_url)->toBe($archiveUrl);
    expect($policy->is_enabled)->toBeTrue();
})->with('desktop release platforms');

it('requires store_url for mobile release policies', function (string $platform) {
    $adminUser = billingReadyUser([
        'email' => 'admin@example.com',
    ]);

    AdminDesignation::factory()->create([
        'email' => $adminUser->email,
    ]);

    Livewire::actingAs($adminUser)
        ->test('app-release-policy-panel')
        ->set("policies.{$platform}.latest_version", '1.4.0')
        ->set("policies.{$platform}.latest_build_number", 42)
        ->set("policies.{$platform}.store_url", '')
        ->call('save', $platform)
        ->assertHasErrors(["policies.{$platform}.store_url" => ['required']]);
})->with('mobile release platforms');

it('requires archive_url for desktop release policies', function (string $platform) {
    $adminUser = billingReadyUser([
        'email' => 'admin@example.com',
    ]);

    AdminDesignation::factory()->create([
        'email' => $adminUser->email,
    ]);

    Livewire::actingAs($adminUser)
        ->test('app-release-policy-panel')
        ->set("policies.{$platform}.latest_version", '1.4.0')
        ->set("policies.{$platform}.latest_build_number", 42)
        ->set("policies.{$platform}.archive_url", '')
        ->call('save', $platform)
        ->assertHasErrors(["policies.{$platform}.archive_url" => ['required']]);
})->with('desktop release platforms');

it('blocks non-admins from opening the app release policy panel', function () {
    Livewire::actingAs(billingReadyUser())
        ->test('app-release-policy-panel')
        ->assertStatus(403);
});
