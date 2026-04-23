<x-guest-layout>
    <div class="mb-4 text-sm text-ink-muted dark:text-ink-soft">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <x-ui.banner class="mb-4 font-medium">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </x-ui.banner>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="rounded-md text-sm font-medium text-ink underline decoration-ink-border-dark underline-offset-4 transition hover:text-ink-muted focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 focus:ring-offset-canvas-light dark:text-ink-inverse dark:decoration-ink-border dark:hover:text-ink-soft dark:focus:ring-brand-300 dark:focus:ring-offset-canvas-dark">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
