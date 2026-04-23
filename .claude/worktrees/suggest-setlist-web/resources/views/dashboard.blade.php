<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-ink dark:text-ink-inverse">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <livewire:dashboard-page />
</x-app-layout>
