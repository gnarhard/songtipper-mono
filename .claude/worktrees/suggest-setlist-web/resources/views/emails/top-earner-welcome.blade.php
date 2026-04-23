<x-mail::message>
# You're a Top Earner!

Hi {{ Str::before($user->name, ' ') }},

You earned over **$2,500** in tips this month — you're now a Top Earner!

Your verified badge is live on your profile.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
