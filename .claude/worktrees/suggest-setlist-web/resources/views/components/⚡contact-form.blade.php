<?php

use App\Mail\ContactFormSubmission;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|string|min:10|max:5000')]
    public string $message = '';

    // Honeypot field — must remain empty for real submissions.
    public string $website = '';

    public bool $submitted = false;

    // Timestamp set on mount; locked so clients cannot tamper with it.
    #[Locked]
    public int $loadedAt = 0;

    public function mount(): void
    {
        $this->loadedAt = now()->timestamp;
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="space-y-6" aria-hidden="true">
            <div class="space-y-2">
                <div class="h-4 w-16 rounded bg-ink-subtle/20 dark:bg-ink-soft/20"></div>
                <div class="h-11 w-full rounded-lg bg-surface dark:bg-surface-elevated"></div>
            </div>
            <div class="space-y-2">
                <div class="h-4 w-16 rounded bg-ink-subtle/20 dark:bg-ink-soft/20"></div>
                <div class="h-11 w-full rounded-lg bg-surface dark:bg-surface-elevated"></div>
            </div>
            <div class="space-y-2">
                <div class="h-4 w-20 rounded bg-ink-subtle/20 dark:bg-ink-soft/20"></div>
                <div class="h-32 w-full rounded-lg bg-surface dark:bg-surface-elevated"></div>
            </div>
            <div class="h-11 w-full rounded-xl bg-brand-200/50 dark:bg-brand-500/20"></div>
        </div>
        HTML;
    }

    public function submit(): void
    {
        // Silently discard bot submissions without revealing the reason.
        if ($this->website !== '' || (now()->timestamp - $this->loadedAt) < 3) {
            $this->submitted = true;
            $this->reset(['name', 'email', 'message', 'website']);

            return;
        }

        $this->validate();

        Mail::to(config('mail.admin_address'))
            ->send(new ContactFormSubmission(
                name: $this->name,
                email: $this->email,
                messageContent: $this->message
            ));

        $this->submitted = true;
        $this->reset(['name', 'email', 'message', 'website']);
    }
};
?>

<div>
    @if ($submitted)
        <div class="rounded-lg bg-success-50 p-6 text-center dark:bg-success-900/20">
            <svg class="mx-auto h-12 w-12 text-success-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-semibold text-success-700 dark:text-success-200">Message Sent!</h3>
            <p class="mt-2 text-success-700 dark:text-success-300">Thank you for reaching out. We'll get back to you soon.</p>
            <button
                wire:click="$set('submitted', false)"
                class="mt-4 text-sm text-success-600 hover:underline dark:text-success-300"
            >
                Send another message
            </button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6">
            {{-- Honeypot: hidden from real users; bots that fill it are silently discarded. --}}
            <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" wire:model="website" autocomplete="off" tabindex="-1" />
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-ink-muted dark:text-ink-soft">
                    Name
                </label>
                <x-text-input
                    type="text"
                    id="name"
                    wire:model="name"
                    class="mt-1 block w-full"
                    placeholder="Your name"
                />
                @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-ink-muted dark:text-ink-soft">
                    Email
                </label>
                <x-text-input
                    type="email"
                    id="email"
                    wire:model="email"
                    class="mt-1 block w-full"
                    placeholder="you@example.com"
                />
                @error('email')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="message" class="block text-sm font-medium text-ink-muted dark:text-ink-soft">
                    Message
                </label>
                <x-textarea-input
                    id="message"
                    wire:model="message"
                    rows="4"
                    class="mt-1 block w-full"
                    placeholder="How can we help you?"
                ></x-textarea-input>
                @error('message')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <x-primary-button type="submit" class="w-full disabled:cursor-not-allowed disabled:opacity-50" wire:loading.attr="disabled">
                <span wire:loading.remove>Send Message</span>
                <span wire:loading class="inline-flex items-center">
                    <svg class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="ml-1">Sending...</span>
                </span>
            </x-primary-button>
        </form>
    @endif
</div>
