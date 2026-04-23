<x-mail::message>
# Complimentary {{ $planLabel }} access

You have been offered complimentary {{ strtolower($durationLabel) }} access to the {{ $planLabel }} plan on {{ config('app.name') }}.

Use this exact email address to claim it:

**{{ $recipientEmail }}**

No coupon code is required. We will apply the offer automatically when you sign in or create your account with that email.

@if ($billingDiscountEndsAt !== null)
This complimentary access is active through **{{ $billingDiscountEndsAt->toFormattedDateString() }}**.
@endif

<x-mail::button :url="$registerUrl">
Create Your Account
</x-mail::button>

<x-mail::button :url="$loginUrl" color="secondary">
Sign In
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
