<x-mail::message>
# Congrats, {{ Str::before($user->name, ' ') }}!

You've earned over **$200** through {{ config('app.name') }}.

Your Pro subscription will start soon. Choose between:

- **$199.99/year** (recommended)
- **$19.99/month**

You have **14 days** to set up your payment method. Failure to do so will result in the audience request feature being disabled.

<x-mail::button :url="$billingUrl">
Set Up Billing
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
