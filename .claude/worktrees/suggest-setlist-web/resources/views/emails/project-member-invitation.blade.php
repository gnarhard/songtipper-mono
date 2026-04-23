<x-mail::message>
# You've Been Added to a Project

Hi {{ Str::before($invitedUser->name, ' ') }},

**{{ $ownerName }}** added you to **{{ $project->name }}** on Song Tipper.

Open the app and switch to this project to start collaborating.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
