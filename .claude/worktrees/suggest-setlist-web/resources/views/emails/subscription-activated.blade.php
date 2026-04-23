<x-mail::message>
# Welcome to Pro!

Hi {{ Str::before($user->name, ' ') }},

You're all set. Your Pro subscription is active.

You can manage or cancel anytime from your billing portal in the dashboard.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
