<x-mail::message>
# Action needed

Hi {{ Str::before($user->name, ' ') }},

Your 14-day window has passed. Subscribe now to keep receiving audience song requests and tips.

<x-mail::button :url="$billingUrl">
Subscribe Now
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
