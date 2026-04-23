<x-mail::message>
# Hey {{ Str::before($user->name, ' ') }},

You're doing great on {{ config('app.name') }}!

Did you know you could save with our yearly plan? At **$199.99/year**, you'd pay less than **$19.99/month**.

<x-mail::button :url="$billingUrl">
Switch to Yearly
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
