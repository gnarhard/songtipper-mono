<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-ink dark:text-ink-inverse">
            {{ __('Admin: Song Integrity') }}
        </h2>
    </x-slot>

    <livewire:admin-song-integrity-page />
</x-app-layout>
