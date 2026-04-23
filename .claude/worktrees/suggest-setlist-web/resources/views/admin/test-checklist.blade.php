<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-ink dark:text-ink-inverse">
            {{ __('Admin: Test Checklist') }}
        </h2>
    </x-slot>

    <livewire:admin-test-checklist-page />
</x-app-layout>
