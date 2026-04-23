<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-ink dark:text-ink-inverse">
            {{ __('Admin: Master Songs') }}
        </h2>
    </x-slot>

    <livewire:admin-songs-page />
</x-app-layout>
