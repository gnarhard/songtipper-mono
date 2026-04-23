<?php

use App\Models\AppReleasePolicy;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $policies = [];

    /**
     * @var array<string, string|null>
     */
    public array $saveStatus = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $this->policies = $this->buildPolicies();
        $this->saveStatus = collect(AppReleasePolicy::platforms())
            ->mapWithKeys(fn (string $platform): array => [$platform => null])
            ->all();
    }

    #[Computed]
    public function platformDefinitions(): array
    {
        return [
            AppReleasePolicy::PLATFORM_IOS => [
                'label' => 'iOS',
                'url_label' => 'Store URL',
                'url_key' => 'store_url',
                'url_placeholder' => 'https://apps.apple.com/app/id123456789',
            ],
            AppReleasePolicy::PLATFORM_ANDROID => [
                'label' => 'Android',
                'url_label' => 'Store URL',
                'url_key' => 'store_url',
                'url_placeholder' => 'https://play.google.com/store/apps/details?id=com.songtipper.app',
            ],
            AppReleasePolicy::PLATFORM_MACOS => [
                'label' => 'macOS',
                'url_label' => 'Archive URL',
                'url_key' => 'archive_url',
                'url_placeholder' => 'https://downloads.songtipper.com/macos/app-archive.json',
            ],
            AppReleasePolicy::PLATFORM_WINDOWS => [
                'label' => 'Windows',
                'url_label' => 'Archive URL',
                'url_key' => 'archive_url',
                'url_placeholder' => 'https://downloads.songtipper.com/windows/app-archive.json',
            ],
            AppReleasePolicy::PLATFORM_LINUX => [
                'label' => 'Linux',
                'url_label' => 'Archive URL',
                'url_key' => 'archive_url',
                'url_placeholder' => 'https://downloads.songtipper.com/linux/app-archive.json',
            ],
        ];
    }

    public function save(string $platform): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $platform = strtolower(trim($platform));

        abort_unless(AppReleasePolicy::isSupportedPlatform($platform), 404);

        $this->resetValidation();
        $this->saveStatus[$platform] = null;

        $validated = $this->validate($this->rulesForPlatform($platform));
        $payload = $validated['policies'][$platform];

        AppReleasePolicy::query()->updateOrCreate(
            ['platform' => $platform],
            [
                'latest_version' => (string) $payload['latest_version'],
                'latest_build_number' => (int) $payload['latest_build_number'],
                'store_url' => $this->nullIfBlank($payload['store_url'] ?? null),
                'archive_url' => $this->nullIfBlank($payload['archive_url'] ?? null),
                'is_enabled' => (bool) $payload['is_enabled'],
            ]
        );

        $this->policies = $this->buildPolicies();
        $this->saveStatus[$platform] = "Saved {$this->definitionForPlatform($platform)['label']} release policy.";
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rulesForPlatform(string $platform): array
    {
        $storeUrlRules = ['nullable', 'url', 'max:2048'];
        $archiveUrlRules = ['nullable', 'url', 'max:2048'];

        if (AppReleasePolicy::isMobilePlatform($platform)) {
            array_unshift($storeUrlRules, 'required');
        }

        if (AppReleasePolicy::isDesktopPlatform($platform)) {
            array_unshift($archiveUrlRules, 'required');
        }

        return [
            "policies.{$platform}.latest_version" => [
                'required',
                'string',
                'max:32',
                'regex:/^\d+\.\d+\.\d+$/',
            ],
            "policies.{$platform}.latest_build_number" => [
                'required',
                'integer',
                'min:0',
            ],
            "policies.{$platform}.store_url" => $storeUrlRules,
            "policies.{$platform}.archive_url" => $archiveUrlRules,
            "policies.{$platform}.is_enabled" => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildPolicies(): array
    {
        $storedPolicies = AppReleasePolicy::query()
            ->get()
            ->keyBy('platform');

        $policies = [];

        foreach (AppReleasePolicy::platforms() as $platform) {
            /** @var AppReleasePolicy|null $storedPolicy */
            $storedPolicy = $storedPolicies->get($platform);

            $policies[$platform] = [
                'latest_version' => (string) ($storedPolicy?->latest_version ?? '0.0.0'),
                'latest_build_number' => (int) ($storedPolicy?->latest_build_number ?? 0),
                'store_url' => $storedPolicy?->store_url,
                'archive_url' => $storedPolicy?->archive_url,
                'is_enabled' => (bool) ($storedPolicy?->is_enabled ?? false),
            ];
        }

        return $policies;
    }

    /**
     * @return array<string, mixed>
     */
    private function definitionForPlatform(string $platform): array
    {
        return match ($platform) {
            AppReleasePolicy::PLATFORM_IOS => [
                'label' => 'iOS',
                'url_label' => 'Store URL',
                'url_key' => 'store_url',
                'url_placeholder' => 'https://apps.apple.com/app/id123456789',
            ],
            AppReleasePolicy::PLATFORM_ANDROID => [
                'label' => 'Android',
                'url_label' => 'Store URL',
                'url_key' => 'store_url',
                'url_placeholder' => 'https://play.google.com/store/apps/details?id=com.songtipper.app',
            ],
            AppReleasePolicy::PLATFORM_MACOS => [
                'label' => 'macOS',
                'url_label' => 'Archive URL',
                'url_key' => 'archive_url',
                'url_placeholder' => 'https://downloads.songtipper.com/macos/app-archive.json',
            ],
            AppReleasePolicy::PLATFORM_WINDOWS => [
                'label' => 'Windows',
                'url_label' => 'Archive URL',
                'url_key' => 'archive_url',
                'url_placeholder' => 'https://downloads.songtipper.com/windows/app-archive.json',
            ],
            AppReleasePolicy::PLATFORM_LINUX => [
                'label' => 'Linux',
                'url_label' => 'Archive URL',
                'url_key' => 'archive_url',
                'url_placeholder' => 'https://downloads.songtipper.com/linux/app-archive.json',
            ],
        };
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
};
?>

<div
    class="rounded-2xl border border-ink-border/80 border-brand-100/80 bg-surface/95 shadow-sm backdrop-blur-sm dark:border-ink-border-dark/80 dark:border-brand-900/70 dark:bg-surface-inverse/90"
    data-test="app-release-policy-panel"
>
    <div class="border-b border-ink-border px-6 py-5 dark:border-ink-border-dark">
        <h3 class="text-lg font-semibold text-ink dark:text-ink-inverse">App Release Policies</h3>
        <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
            Configure the public startup version policy for each supported app platform.
            Mobile rows use store links. Desktop rows use hosted updater archive manifests.
        </p>
    </div>

    <div class="space-y-4 p-6">
        @foreach ($this->platformDefinitions as $platform => $definition)
            <form
                wire:submit="save('{{ $platform }}')"
                class="rounded-2xl border border-ink-border/80 bg-surface-muted/80 p-5 dark:border-ink-border-dark/80 dark:bg-surface-elevated/70"
                data-test="app-release-policy-row-{{ $platform }}"
            >
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="xl:w-48">
                        <p class="text-base font-semibold text-ink dark:text-ink-inverse">{{ $definition['label'] }}</p>
                        <p class="mt-1 text-sm text-ink-muted dark:text-ink-soft">
                            {{ $definition['url_label'] }} is required for this platform.
                        </p>
                    </div>

                    <div class="grid flex-1 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label for="release-version-{{ $platform }}" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                                Latest Version
                            </label>
                            <x-text-input
                                id="release-version-{{ $platform }}"
                                type="text"
                                wire:model="policies.{{ $platform }}.latest_version"
                                class="w-full"
                                placeholder="1.2.3"
                                data-test="release-version-{{ $platform }}"
                            />
                            @error("policies.$platform.latest_version")
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="release-build-{{ $platform }}" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                                Latest Build
                            </label>
                            <x-text-input
                                id="release-build-{{ $platform }}"
                                type="number"
                                min="0"
                                wire:model="policies.{{ $platform }}.latest_build_number"
                                class="w-full"
                                data-test="release-build-{{ $platform }}"
                            />
                            @error("policies.$platform.latest_build_number")
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="release-url-{{ $platform }}" class="mb-1 block text-sm font-medium text-ink-muted dark:text-ink-soft">
                                {{ $definition['url_label'] }}
                            </label>
                            <x-text-input
                                id="release-url-{{ $platform }}"
                                type="url"
                                wire:model="policies.{{ $platform }}.{{ $definition['url_key'] }}"
                                class="w-full"
                                placeholder="{{ $definition['url_placeholder'] }}"
                                data-test="release-url-{{ $platform }}"
                            />
                            @error("policies.$platform.store_url")
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            @error("policies.$platform.archive_url")
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex min-w-52 flex-col gap-4 xl:items-end">
                        <label class="inline-flex items-center gap-3 text-sm font-medium text-ink dark:text-ink-inverse">
                            <input
                                type="checkbox"
                                wire:model="policies.{{ $platform }}.is_enabled"
                                class="h-4 w-4 rounded border-ink-border text-brand focus:ring-brand dark:border-ink-border-dark dark:bg-canvas-dark"
                                data-test="release-enabled-{{ $platform }}"
                            />
                            Enabled
                        </label>

                        @if ($saveStatus[$platform])
                            <p class="text-sm text-success-700 dark:text-success-300" data-test="release-policy-success-{{ $platform }}">
                                {{ $saveStatus[$platform] }}
                            </p>
                        @endif

                        <x-primary-button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save('{{ $platform }}')"
                            class="justify-center px-4 py-2.5 disabled:cursor-not-allowed disabled:opacity-60"
                            data-test="save-release-policy-{{ $platform }}"
                        >
                            <span wire:loading.remove wire:target="save('{{ $platform }}')">Save {{ $definition['label'] }}</span>
                            <span wire:loading wire:target="save('{{ $platform }}')">Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </form>
        @endforeach
    </div>
</div>
