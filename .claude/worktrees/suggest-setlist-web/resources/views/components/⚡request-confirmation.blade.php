<?php

use Livewire\Component;

new class extends Component
{
    public string $status = 'unknown';
    public string $submission = 'request';
    public ?string $paymentIntentId = null;
    public ?string $projectSlug = null;

    public function mount(): void
    {
        $this->status = request()->query('redirect_status', 'unknown');
        $this->submission = request()->query('submission', 'request');
        $this->paymentIntentId = request()->query('payment_intent');
        $this->projectSlug = request()->query('project_slug');
    }
};
?>

<x-ui.shell class="flex items-center justify-center">
    <div class="max-w-md w-full mx-auto px-4">
        <x-ui.panel class="overflow-hidden p-8 text-center">
            @if ($status === 'succeeded')
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-success-50 dark:bg-success-900/30">
                    <svg class="h-8 w-8 text-success-500 dark:text-success-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                @if ($submission === 'tip')
                    <h1 class="mb-2 text-2xl font-bold text-ink dark:text-ink-inverse">Tip Sent!</h1>
                    <p class="mb-6 text-ink-muted dark:text-ink-soft">
                        Thanks for supporting the performer.
                    </p>
                @else
                    <h1 class="mb-2 text-2xl font-bold text-ink dark:text-ink-inverse">Request Submitted!</h1>
                    <p class="mb-6 text-ink-muted dark:text-ink-soft">
                        Your song request has been added to the queue. The performer will see it shortly!
                    </p>
                @endif
            @elseif ($status === 'processing')
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-info-50 dark:bg-info-900/30">
                    <svg class="h-8 w-8 animate-spin text-info-500 dark:text-info-300" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                @if ($submission === 'tip')
                    <h1 class="mb-2 text-2xl font-bold text-ink dark:text-ink-inverse">Processing Tip</h1>
                    <p class="mb-6 text-ink-muted dark:text-ink-soft">
                        Your payment is being processed. We will confirm your tip shortly.
                    </p>
                @else
                    <h1 class="mb-2 text-2xl font-bold text-ink dark:text-ink-inverse">Processing Payment</h1>
                    <p class="mb-6 text-ink-muted dark:text-ink-soft">
                        Your payment is being processed. Your request will be added to the queue once confirmed.
                    </p>
                @endif
            @else
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-danger-100 dark:bg-danger-900/30">
                    <svg class="h-8 w-8 text-danger-600 dark:text-danger-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                <h1 class="mb-2 text-2xl font-bold text-ink dark:text-ink-inverse">Payment Failed</h1>
                <p class="mb-6 text-ink-muted dark:text-ink-soft">
                    We couldn't process your payment. Please try again.
                </p>
            @endif

            <x-ui.button-link
                :href="$projectSlug ? route('project.page', ['projectSlug' => $projectSlug]) : route('home')"
                class="px-4 py-2"
            >
                {{ $projectSlug ? 'Back to Repertoire' : 'Return Home' }}
            </x-ui.button-link>

            @if ($status === 'succeeded' && $submission === 'tip' && $projectSlug)
                <x-ui.button-link
                    :href="route('project.page', ['projectSlug' => $projectSlug])"
                    variant="secondary"
                    class="mt-3"
                >
                    Request a Song Too
                </x-ui.button-link>
            @endif
        </x-ui.panel>
    </div>
</x-ui.shell>
